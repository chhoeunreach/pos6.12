<?php

namespace App\Repositories;

use App\ExportHistory;
use Illuminate\Support\Carbon;

class ExportRepository
{
    public function find(int $id): ExportHistory
    {
        return ExportHistory::findOrFail($id);
    }

    public function create(int $businessId, int $userId, string $type, array $filters, string $format): ExportHistory
    {
        return ExportHistory::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'type' => $type,
            'filters' => $filters,
            'format' => $format,
            'status' => 'queued',
            'progress' => 0,
            'download_expires_at' => now()->addHours(config('async_export.download_ttl_hours', 72)),
        ]);
    }

    public function forBusiness(int $id, int $businessId): ExportHistory
    {
        return ExportHistory::where('business_id', $businessId)->findOrFail($id);
    }

    public function recent(int $businessId, int $limit = 30)
    {
        return ExportHistory::where('business_id', $businessId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function markProcessing(ExportHistory $export, int $totalRows, string $filename, string $path): void
    {
        $export->update([
            'status' => 'processing',
            'started_at' => $export->started_at ?: now(),
            'total_rows' => $totalRows,
            'filename' => $filename,
            'path' => $path,
        ]);
    }

    public function updateProgress(ExportHistory $export, int $processedRows, int $totalRows): void
    {
        $export->update([
            'processed_rows' => $processedRows,
            'total_rows' => $totalRows,
            'progress' => $totalRows > 0 ? min(99, (int) floor(($processedRows / $totalRows) * 100)) : 99,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markCompleted(ExportHistory $export): void
    {
        $export->update([
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markFailed(ExportHistory $export, string $message): void
    {
        $export->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}
