<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileUploadService
{
    public function uploadChatAttachment(UploadedFile $file, string $category = 'chat_attachment', ?string $disk = null): array
    {
        $disk = $disk ?: (config('filesystems.default') ?: 'public');

        $path = Storage::disk($disk)->putFile('loan-management/chat', $file);
        $url = method_exists(Storage::disk($disk), 'url') ? Storage::disk($disk)->url($path) : $path;

        $payload = [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_url' => $url,
            'file_mime' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'category' => $category,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $fileId = null;
        try {
            // Best-effort insert into loan_files; do not fail chat if schema differs.
            $fileId = DB::connection('mysql_loan')->table('loan_files')->insertGetId($payload);
        } catch (Throwable $e) {
            $fileId = null;
        }

        return [
            'id' => $fileId,
            'url' => $url,
            'name' => $payload['file_name'],
            'mime' => $payload['file_mime'],
            'size' => (int)$payload['file_size'],
            'path' => $path,
            'disk' => $disk,
        ];
    }
}

