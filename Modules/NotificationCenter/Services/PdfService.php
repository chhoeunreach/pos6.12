<?php

namespace Modules\NotificationCenter\Services;

use App\Services\WkhtmltopdfPdfService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PdfService
{
    protected ?WkhtmltopdfPdfService $engine = null;

    protected string $tempDir;

    public function __construct()
    {
        $this->tempDir = config('notificationcenter.temp_folder', storage_path('app/notification-temp'));
        if (! File::exists($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }
    }

    public function generate(string $view, array $data, ?string $prefix = null): ?string
    {
        $prefix = $prefix ?: Str::random(16);
        $outputPath = $this->tempDir.DIRECTORY_SEPARATOR.$prefix.'.pdf';

        try {
            $engine = $this->engine();
            $engine->saveViewToPdf($view, $data, $outputPath);

            if (File::exists($outputPath) && File::size($outputPath) > 0) {
                return $outputPath;
            }

            return null;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('NotificationCenter PdfService generate failed', [
                'view' => $view,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function cleanupOldFiles(?int $olderThanDays = null): int
    {
        $days = $olderThanDays ?? (int) config('notificationcenter.cleanup_days', 7);
        $cutoff = now()->subDays($days);
        $deleted = 0;

        foreach (File::files($this->tempDir) as $file) {
            if ($file->getMimeType() === 'application/pdf' && $file->getCTime() < $cutoff->timestamp) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        return $deleted;
    }

    protected function engine(): WkhtmltopdfPdfService
    {
        if ($this->engine === null) {
            $this->engine = app(WkhtmltopdfPdfService::class);
        }

        return $this->engine;
    }
}
