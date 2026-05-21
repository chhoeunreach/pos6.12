<?php

namespace App\Jobs;

use App\Repositories\ExportRepository;
use App\Services\Exports\EnterpriseExportManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessEnterpriseExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 3;

    public function __construct(public int $exportId)
    {
        $this->onQueue(config('async_export.queue', 'exports'));
    }

    public function handle(EnterpriseExportManager $manager, ExportRepository $exports): void
    {
        $export = $exports->find($this->exportId);
        $exporter = $manager->exporter($export->type);
        $writer = $manager->writer($export->format);

        $disk = config('async_export.disk', 'local');
        $directory = 'exports/'.date('Y/m/d');
        Storage::disk($disk)->makeDirectory($directory);

        if (! method_exists(Storage::disk($disk), 'path')) {
            throw new \RuntimeException('Enterprise exports require a local filesystem disk.');
        }

        $filename = $this->filename($export->type, $export->format);
        $relativePath = $directory.'/'.$filename;
        $absolutePath = Storage::disk($disk)->path($relativePath);

        $query = $exporter->query((int) $export->business_id, (array) $export->filters);
        $totalRows = (clone $query)->count();

        $exports->markProcessing($export, $totalRows, $filename, $relativePath);

        $processedRows = 0;
        $writerOpened = false;

        try {
            $writer->open($absolutePath, $exporter->headings());
            $writerOpened = true;

            $query->chunkById(
                (int) config('async_export.chunk_size', 2000),
                function ($rows) use ($exporter, $writer, $exports, $export, $totalRows, &$processedRows) {
                    $writer->addRows($rows->map(fn ($row) => $exporter->map($row))->all());
                    $processedRows += $rows->count();
                    $exports->updateProgress($export, $processedRows, $totalRows);
                },
                $exporter->chunkColumn(),
                'id'
            );

            $writer->close();
            $exports->markCompleted($export->fresh());
        } catch (Throwable $e) {
            if ($writerOpened) {
                try {
                    $writer->close();
                } catch (Throwable $closeException) {
                    //
                }
            }

            Storage::disk($disk)->delete($relativePath);
            $exports->markFailed($export->fresh(), $e->getMessage());

            throw $e;
        }
    }

    protected function filename(string $type, string $format): string
    {
        return $type.'_'.now()->format('Ymd_His').'.'.$format;
    }
}
