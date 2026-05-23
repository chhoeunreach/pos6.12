<?php

namespace App\Jobs;

use App\Repositories\ImportRepository;
use App\Services\Imports\EnterpriseImportManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\LoanManagement\Services\LoanImportExportService;
use Throwable;

class ProcessEnterpriseImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public int $importId)
    {
        $this->onQueue(config('async_import.queue', 'imports'));
    }

    public function handle(EnterpriseImportManager $manager, ImportRepository $imports, LoanImportExportService $loanImports): void
    {
        $import = $imports->find($this->importId);
        $imports->markProcessing($import);

        $disk = config('async_import.disk', 'local');
        $absolutePath = Storage::disk($disk)->path($import->stored_path);

        try {
            $file = new UploadedFile($absolutePath, $import->filename, null, null, true);
            $result = $loanImports->import(
                $manager->loanType($import->type),
                $file,
                (int) $import->user_id,
                (string) $import->duplicate_mode
            );

            $imports->markCompleted(
                $import->fresh(),
                (int) ($result['total_rows'] ?? 0),
                (int) (($result['imported_rows'] ?? 0) + ($result['skipped_rows'] ?? 0)),
                (int) ($result['invalid_rows'] ?? 0),
                ['loan_batch_id' => $result['batch_id'] ?? null, 'result' => $result]
            );
        } catch (Throwable $e) {
            $imports->markFailed($import->fresh(), $e->getMessage());

            throw $e;
        }
    }
}
