<?php

namespace Modules\UserBackupRestore\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\UserBackupRestore\Services\UserExportService;
use Modules\UserBackupRestore\Services\UserImportService;

class UserBackupRestoreController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        return view('userbackuprestore::index');
    }

    public function export(Request $request, UserExportService $exportService)
    {
        $this->authorizeAccess('export');

        $validated = $request->validate([
            'active_only' => ['nullable', 'boolean'],
            'include_inactive' => ['nullable', 'boolean'],
            'include_roles' => ['nullable', 'boolean'],
            'include_location_permissions' => ['nullable', 'boolean'],
            'include_passwords' => ['nullable', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $business_id = (int) $request->session()->get('user.business_id');
        $user_id = (int) $request->session()->get('user.id');

        $options = [
            'active_only' => (bool) ($validated['active_only'] ?? false),
            'include_inactive' => (bool) ($validated['include_inactive'] ?? false),
            'include_roles' => (bool) ($validated['include_roles'] ?? false),
            'include_location_permissions' => (bool) ($validated['include_location_permissions'] ?? false),
            'include_passwords' => (bool) ($validated['include_passwords'] ?? false),
            'user_ids' => $validated['user_ids'] ?? [],
        ];

        $zipPath = $exportService->exportUsers($business_id, $options);
        $downloadName = 'user_backup_' . now()->format('Ymd_His') . '.zip';

        Log::info('UserBackupRestore export created', [
            'business_id' => $business_id,
            'user_id' => $user_id,
            'options' => $options,
            'zip' => basename($zipPath),
        ]);

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }

    public function preview(Request $request, UserImportService $importService)
    {
        $this->authorizeAccess('import');

        $validated = $request->validate([
            'backup_zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'mode' => ['nullable', 'in:insert_only,update_existing,insert_update'],
            'password_option' => ['nullable', 'in:random,default,restore_hash'],
            'default_password' => ['nullable', 'string', 'min:6'],
        ]);

        $file = $validated['backup_zip'];
        $storedPath = $file->storeAs(
            'temp/user_backup_restore/uploads',
            'upload_' . now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName())
        );

        $business_id = (int) $request->session()->get('user.business_id');

        $preview = $importService->preview(storage_path('app/' . $storedPath), $business_id);
        $preview['stored_path'] = $storedPath;
        $preview['mode'] = $validated['mode'] ?? 'insert_only';
        $preview['password_option'] = $validated['password_option'] ?? 'random';
        $preview['default_password'] = $validated['default_password'] ?? '12345678';

        return view('userbackuprestore::preview', $preview);
    }

    public function import(Request $request, UserImportService $importService)
    {
        $this->authorizeAccess('import');

        $validated = $request->validate([
            'stored_path' => ['required', 'string'],
            'mode' => ['required', 'in:insert_only,update_existing,insert_update'],
            'password_option' => ['required', 'in:random,default,restore_hash'],
            'default_password' => ['nullable', 'string', 'min:6'],
        ]);

        $zipPath = storage_path('app/' . ltrim($validated['stored_path'], '/'));
        if (! is_file($zipPath)) {
            throw ValidationException::withMessages(['stored_path' => 'Uploaded ZIP was not found. Please re-upload and try again.']);
        }

        $business_id = (int) $request->session()->get('user.business_id');
        $user_id = (int) $request->session()->get('user.id');

        $options = [
            'password_option' => $validated['password_option'],
            'default_password' => $validated['default_password'] ?? '12345678',
        ];

        $result = $importService->import($zipPath, $business_id, $validated['mode'], $options);

        Log::info('UserBackupRestore import completed', [
            'business_id' => $business_id,
            'user_id' => $user_id,
            'mode' => $validated['mode'],
            'options' => $options,
            'summary' => $result,
        ]);

        $msg = 'Import completed. Inserted: ' . ($result['inserted'] ?? 0)
            . ', Updated: ' . ($result['updated'] ?? 0)
            . ', Skipped: ' . ($result['skipped'] ?? 0)
            . ', Failed: ' . ($result['failed'] ?? 0);
        if (! empty($result['warnings_count'])) {
            $msg .= ' | Warnings: ' . $result['warnings_count'];
        }

        return redirect()
            ->route('user-backup-restore.index')
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

        $can_fallback = auth()->user()->can('user.view') || auth()->user()->can('user.create');

        $perm_view = auth()->user()->can('user_backup_restore.view');
        $perm_export = auth()->user()->can('user_backup_restore.export');
        $perm_import = auth()->user()->can('user_backup_restore.import');

        if (! ($is_admin || $is_superadmin || $perm_view || $perm_export || $perm_import || $can_fallback)) {
            abort(403);
        }

        if ($action === 'export' && ! ($is_admin || $is_superadmin || $perm_export || auth()->user()->can('backup'))) {
            abort(403);
        }

        if ($action === 'import' && ! ($is_admin || $is_superadmin || $perm_import)) {
            abort(403);
        }
    }
}

