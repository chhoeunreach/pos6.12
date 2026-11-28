<?php

namespace Modules\Social\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Social\Entities\SocialMedia;
use Symfony\Component\Process\Exception\ExecutableNotFoundException;
use Symfony\Component\Process\Process;

class TranscodeFeedVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 600;

    public function __construct(
        public int $mediaId,
        public string $sourceKey,
    ) {
    }

    public function handle(): void
    {
        $media = SocialMedia::query()->find($this->mediaId);
        if (!$media || $media->media_type !== 'video') {
            return;
        }

        if (!empty($media->hls_manifest_key)) {
            return;
        }

        $source = trim((string)($this->sourceKey ?: $media->storage_key));
        if ($source === '') {
            return;
        }

        $disk = Storage::disk(config('social.media_disk', config('filesystems.default', 'public')));
        $tmpDir = rtrim(sys_get_temp_dir(), '/') . '/social_hls_' . $media->id . '_' . uniqid();
        $inputPath = $tmpDir . '/source' . $this->guessExtension($source);

        if (!@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            return;
        }

        try {
            if (!$this->materializeSource($source, $disk, $inputPath)) {
                Log::warning("Social TranscodeFeedVideoJob: unable to fetch source for media {$media->id}");
                return;
            }

            $outputDir = $tmpDir . '/out';
            if (!@mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                return;
            }

            $playlist = $this->runFfmpeg($inputPath, $outputDir);
            if ($playlist === null) {
                return;
            }

            $prefix = 'feed/hls/' . $media->id;
            foreach (glob($outputDir . '/*') ?: [] as $file) {
                $name = basename($file);
                $disk->put($prefix . '/' . $name, fopen($file, 'r'));
            }

            DB::connection('mysql_feed')
                ->table('social_media')
                ->where('id', $media->id)
                ->update([
                    'hls_manifest_key' => $prefix . '/index.m3u8',
                    'updated_at' => now(),
                ]);

            $this->bumpFeedCacheVersion();
        } catch (ExecutableNotFoundException $e) {
            Log::warning('Social TranscodeFeedVideoJob: ffmpeg binary not installed on host, skipping HLS transcode.');
        } catch (\Throwable $e) {
            Log::warning('Social TranscodeFeedVideoJob failed for media ' . $media->id . ': ' . $e->getMessage());
        } finally {
            $this->deleteDirectory($tmpDir);
        }
    }

    protected function materializeSource(string $source, $disk, string $inputPath): bool
    {
        if (preg_match('#^https?://#i', $source)) {
            $context = stream_context_create([
                'http' => ['timeout' => 60, 'follow_location' => 1],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $bytes = @file_get_contents($source, false, $context);
            return $bytes !== false && @file_put_contents($inputPath, $bytes) !== false;
        }

        try {
            if ($disk->exists($source)) {
                $stream = $disk->readStream($source);
                if (is_resource($stream)) {
                    $out = @fopen($inputPath, 'w');
                    if ($out) {
                        stream_copy_to_stream($stream, $out);
                        fclose($out);
                        fclose($stream);
                        return true;
                    }
                    fclose($stream);
                }
                return @file_put_contents($inputPath, $disk->get($source)) !== false;
            }
        } catch (\Throwable $e) {
            Log::warning('Social TranscodeFeedVideoJob source read failed: ' . $e->getMessage());
            return false;
        }

        return false;
    }

    protected function runFfmpeg(string $inputPath, string $outputDir): ?string
    {
        $playlist = $outputDir . '/index.m3u8';
        $segmentPattern = $outputDir . '/seg_%04d.ts';

        $process = new Process([
            'ffmpeg', '-y', '-hide_banner', '-loglevel', 'error',
            '-i', $inputPath,
            '-vf', "scale=-2:'min(720,ih)'",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
            '-c:a', 'aac', '-b:a', '96k', '-ac', '2',
            '-f', 'hls',
            '-hls_time', '6',
            '-hls_list_size', '0',
            '-hls_segment_filename', $segmentPattern,
            $playlist,
        ], null, null, 600);

        $process->run();

        if (!$process->isSuccessful() || !is_file($playlist)) {
            Log::warning('Social TranscodeFeedVideoJob ffmpeg failed: ' . substr($process->getErrorOutput() ?: '', 0, 500));
            return null;
        }

        return $playlist;
    }

    protected function guessExtension(string $source): string
    {
        $path = parse_url($source, PHP_URL_PATH) ?: $source;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'mov', 'm4v', 'webm', 'mkv', 'avi', '3gp'], true)
            ? '.' . $ext
            : '.mp4';
    }

    protected function bumpFeedCacheVersion(): void
    {
        try {
            $current = (int)Cache::get('social_feed:version', 1);
            Cache::put('social_feed:version', $current + 1, now()->addDays(7));
        } catch (\Throwable $e) {
            // Ignore cache failure.
        }
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_dir($file)) {
                $this->deleteDirectory($file);
            } else {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
