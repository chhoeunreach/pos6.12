<?php

namespace App\Repositories;

use App\ImportFailure;
use App\ImportHistory;

class ImportRepository
{
    public function create(int $businessId, int $userId, string $type, string $filename, string $storedPath, string $duplicateMode = 'skip', array $metadata = []): ImportHistory
    {
        return ImportHistory::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'type' => $type,
            'filename' => $filename,
            'stored_path' => $storedPath,
            'status' => 'queued',
            'progress' => 0,
            'duplicate_mode' => $duplicateMode,
            'metadata' => $metadata,
        ]);
    }

    public function find(int $id): ImportHistory
    {
        return ImportHistory::findOrFail($id);
    }

    public function forBusiness(int $id, int $businessId): ImportHistory
    {
        return ImportHistory::where('business_id', $businessId)->findOrFail($id);
    }

    public function recent(int $businessId, int $limit = 30)
    {
        return ImportHistory::where('business_id', $businessId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function markProcessing(ImportHistory $import, int $totalRows = 0): void
    {
        $import->update([
            'status' => 'processing',
            'started_at' => $import->started_at ?: now(),
            'total_rows' => $totalRows,
            'progress' => 1,
        ]);
    }

    public function markCompleted(ImportHistory $import, int $totalRows, int $processedRows, int $failedRows, array $metadata = []): void
    {
        $import->update([
            'status' => $failedRows > 0 ? 'completed_with_errors' : 'completed',
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'failed_rows' => $failedRows,
            'progress' => 100,
            'metadata' => array_merge((array) $import->metadata, $metadata),
            'completed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markFailed(ImportHistory $import, string $message): void
    {
        $import->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public function addFailure(ImportHistory $import, int $rowNumber, array $rawData, string $message): ImportFailure
    {
        return ImportFailure::create([
            'import_history_id' => $import->id,
            'row_number' => $rowNumber,
            'raw_data' => $rawData,
            'error_message' => $message,
            'created_at' => now(),
        ]);
    }
}
