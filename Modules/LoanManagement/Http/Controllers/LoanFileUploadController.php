<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class LoanFileUploadController extends Controller
{
    use ApiResponseTrait;

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf',
            'category' => 'nullable|string|max:50',
        ]);

        $file = $request->file('file');
        $disk = 'public';
        $path = $file->store('loan-management/uploads/'.date('Y/m'), $disk);

        $payload = [
            'fileable_type' => 'generic',
            'fileable_id' => 0,
            'category' => $request->input('category', 'general'),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $columns = Schema::connection('mysql_loan')->hasTable('loan_files')
            ? Schema::connection('mysql_loan')->getColumnListing('loan_files')
            : [];
        $safe = array_intersect_key($payload, array_flip($columns));
        $id = DB::connection('mysql_loan')->table('loan_files')->insertGetId($safe);

        return $this->ok('File uploaded', [
            'file_id' => $id,
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
        ]);
    }
}

