<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\LoanManagement\Entities\LoanFile;

class LoanChatUploadService
{
    public function storeChatFile(UploadedFile $file, string $category, ?int $uploadedBy = null): LoanFile
    {
        $disk = 'public';
        $directory = $category === 'chat_audio'
            ? 'loan/chat/audio/'.date('Y/m')
            : 'loan-management/chat/'.date('Y/m');
        $path = $file->store($directory, $disk);
        $url = Storage::disk($disk)->url($path);

        $payload = [
            'fileable_type' => 'loan_chat_message',
            'fileable_id' => 0,
            'category' => $category,
            'file_type' => $category,
            'disk' => $disk,
            'storage_provider' => 'local',
            'path' => $path,
            'url' => $url,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'size' => $file->getSize(),
            'extension' => strtolower((string) $file->getClientOriginalExtension()),
            'uploaded_by' => $uploadedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $columns = Schema::connection('mysql_loan')->hasTable('loan_files')
            ? Schema::connection('mysql_loan')->getColumnListing('loan_files')
            : [];

        $id = DB::connection('mysql_loan')->table('loan_files')->insertGetId(
            array_intersect_key($payload, array_flip($columns))
        );

        return LoanFile::query()->findOrFail($id);
    }

    public function url(LoanFile $file): ?string
    {
        if (! empty($file->url)) {
            return (string) $file->url;
        }

        if (empty($file->path)) {
            return null;
        }

        return Storage::disk($file->disk ?? 'public')->url($file->path);
    }
}
