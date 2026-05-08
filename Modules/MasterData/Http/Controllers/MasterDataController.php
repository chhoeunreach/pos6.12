<?php

namespace Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\MasterData\Services\MasterDataExportService;
use Modules\MasterData\Services\MasterDataImportService;

class MasterDataController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        return view('masterdata::index');
    }

    public function export(Request $request, MasterDataExportService $exportService)
    {
        $this->authorizeAccess('export');

        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['in:users,products,categories,brands,units,taxes,locations,settings'],
            'format' => ['nullable', 'in:zip,sql'],
        ], [
            'sections.required' => 'Please select at least one section to export.',
            'sections.min' => 'Please select at least one section to export.',
        ]);

        $business_id = (int) $request->session()->get('user.business_id');
        $user_id = (int) $request->session()->get('user.id');

        $sections = array_values(array_unique($validated['sections'] ?? []));
        $format = $validated['format'] ?? 'zip';

        if ($format === 'sql') {
            $file = 'masterdata_' . now()->format('Ymd_His') . '.sql';

            Log::info('MasterData SQL export created', [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'sections' => $sections,
            ]);

            return response()->streamDownload(function () use ($exportService, $business_id, $sections) {
                $out = fopen('php://output', 'w');
                $exportService->exportSql($business_id, ['sections' => $sections], function ($line) use ($out) {
                    fwrite($out, (string) $line . "\n");
                });
                fclose($out);
            }, $file, [
                'Content-Type' => 'application/sql; charset=UTF-8',
            ]);
        }

        $zipPath = $exportService->export($business_id, [
            'sections' => $sections,
            'requested_by' => $user_id,
        ]);

        $downloadName = 'master_data_' . now()->format('Ymd_His') . '.zip';

        Log::info('MasterData export created', [
            'business_id' => $business_id,
            'user_id' => $user_id,
            'sections' => $validated['sections'] ?? [],
            'zip' => basename($zipPath),
        ]);

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }

    public function preview(Request $request, MasterDataImportService $importService)
    {
        $this->authorizeAccess('import');

        $validated = $request->validate([
            'backup_zip' => ['required', 'file', 'mimes:zip', 'max:102400'],
            'mode' => ['nullable', 'in:insert_only,update_existing,insert_update'],
        ]);

        $file = $validated['backup_zip'];
        $storedPath = $file->storeAs(
            'temp/master_data/uploads',
            'upload_' . now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName())
        );

        $business_id = (int) $request->session()->get('user.business_id');
        $mode = $validated['mode'] ?? 'insert_only';

        $preview = $importService->preview(storage_path('app/' . $storedPath), $business_id, $mode);
        $preview['stored_path'] = $storedPath;
        $preview['mode'] = $mode;

        return view('masterdata::preview', $preview);
    }

    public function import(Request $request, MasterDataImportService $importService)
    {
        $this->authorizeAccess('import');

        $validated = $request->validate([
            'stored_path' => ['required', 'string'],
            'mode' => ['required', 'in:insert_only,update_existing,insert_update'],
        ]);

        $zipPath = storage_path('app/' . ltrim($validated['stored_path'], '/'));
        if (! is_file($zipPath)) {
            throw ValidationException::withMessages(['stored_path' => 'Uploaded ZIP was not found. Please re-upload and try again.']);
        }

        $business_id = (int) $request->session()->get('user.business_id');
        $user_id = (int) $request->session()->get('user.id');

        $result = $importService->import($zipPath, $business_id, $validated['mode'], [
            'requested_by' => $user_id,
        ]);

        Log::info('MasterData import completed', [
            'business_id' => $business_id,
            'user_id' => $user_id,
            'mode' => $validated['mode'],
            'summary' => $result,
        ]);

        $msg = 'Restore completed. Inserted: ' . ($result['inserted'] ?? 0)
            . ', Updated: ' . ($result['updated'] ?? 0)
            . ', Skipped: ' . ($result['skipped'] ?? 0)
            . ', Failed: ' . ($result['failed'] ?? 0);
        if (! empty($result['warnings_count'])) {
            $msg .= ' | Warnings: ' . $result['warnings_count'];
        }

        return redirect()
            ->route('master-data.index')
            ->with('status', ['success' => 1, 'msg' => $msg]);
    }

    private function authorizeAccess(string $action = 'view'): void
    {
        if (! auth()->check()) {
            abort(403);
        }

        $business_id = (int) request()->session()->get('user.business_id');
        $is_admin = auth()->user()->hasRole('Admin#' . $business_id) ? true : false;
        $is_superadmin = auth()->user()->can('superadmin');

        $perm_view = auth()->user()->can('master_data.view');
        $perm_export = auth()->user()->can('master_data.export');
        $perm_import = auth()->user()->can('master_data.import');

        if (! ($is_admin || $is_superadmin || $perm_view || $perm_export || $perm_import)) {
            abort(403);
        }

        if ($action === 'export' && ! ($is_admin || $is_superadmin || $perm_export)) {
            abort(403);
        }

        if ($action === 'import' && ! ($is_admin || $is_superadmin || $perm_import)) {
            abort(403);
        }
    }
}
