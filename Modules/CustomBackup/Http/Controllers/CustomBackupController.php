<?php

namespace Modules\CustomBackup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Utils\Util;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomBackupController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    public function index()
    {
        $this->authorizeAccess();

        return view('custombackup::backup.custom');
    }

    public function export(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'from_date' => ['required'],
            'to_date' => ['required'],
            'modules' => ['required', 'array', 'min:1'],
        ], [
            'modules.required' => 'Please select at least one option to export.',
            'modules.min' => 'Please select at least one option to export.',
        ]);

        $business_id = $request->session()->get('user.business_id');
        try {
            $from = Carbon::parse($this->commonUtil->uf_date($validated['from_date']))->startOfDay();
            $to = Carbon::parse($this->commonUtil->uf_date($validated['to_date']))->endOfDay();
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'from_date' => 'Invalid date format. Please use the date picker.',
            ]);
        }

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => 'To Date must be greater than or equal to From Date.',
            ]);
        }

        $modules = array_keys(array_filter($validated['modules'] ?? []));

        $file = 'custom_backup_' . now()->format('Ymd_His') . '.sql';

        return response()->streamDownload(function () use ($business_id, $from, $to, $modules) {
            $out = fopen('php://output', 'w');

            $this->writeLine($out, "-- Ultimate POS Custom Backup");
            $this->writeLine($out, "-- Date range: {$from->toDateTimeString()} -> {$to->toDateTimeString()}");
            $this->writeLine($out, "SET FOREIGN_KEY_CHECKS=0;");
            $this->writeLine($out, "");

            foreach ($modules as $module) {
                switch ($module) {
                    case 'products':
                        $this->exportProducts($out, $business_id, $from, $to);
                        break;
                    case 'repair':
                        $this->exportRepair($out, $business_id, $from, $to);
                        break;
                    case 'purchases':
                        $this->exportTransactionsByType($out, $business_id, $from, $to, 'purchase');
                        break;
                    case 'sell':
                        $this->exportTransactionsByType($out, $business_id, $from, $to, 'sell');
                        break;
                    case 'stock_transfers':
                        $this->exportStockTransfers($out, $business_id, $from, $to);
                        break;
                    case 'stock_adjustment':
                        $this->exportTransactionsByType($out, $business_id, $from, $to, 'stock_adjustment');
                        break;
                    case 'expenses':
                        $this->exportTransactionsByType($out, $business_id, $from, $to, 'expense');
                        break;
                    case 'users_permissions':
                        $this->exportUsersPermissions($out, $business_id, $from, $to);
                        break;
                }
            }

            $this->writeLine($out, "");
            $this->writeLine($out, "SET FOREIGN_KEY_CHECKS=1;");
            fclose($out);
        }, $file, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    public function showImportForm()
    {
        $this->authorizeAccess();

        return view('custombackup::backup.import');
    }

    public function import(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'backup_sql' => ['required', 'file', 'mimes:sql,txt', 'max:' . (int) config('constants.custom_backup_import_max_kb', 10240)],
            'confirm_risk' => ['accepted'],
            'conflict_mode' => ['required', 'in:insert,ignore,replace'],
            'user_conflict' => ['required', 'in:skip,update,replace'],
        ], [
            'confirm_risk.accepted' => 'Please confirm you understand the risks before importing.',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');
        $conflict_mode = $request->input('conflict_mode', 'insert');
        $user_conflict = $request->input('user_conflict', 'skip');
        $file = $request->file('backup_sql');
        $original_name = $file->getClientOriginalName();

        $history_id = null;
        $error_message = null;

        try {
            // 1) Create automatic full DB backup
            $backup_path = $this->createPreImportBackup($business_id);

            // 2) Store uploaded file for audit
            $stored_name = 'custom_import_' . now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $original_name);
            $stored_path = $file->storeAs('backups/custom_imports', $stored_name);

            // 3) Log history (pending)
            $history_id = DB::table('custom_backup_import_histories')->insertGetId([
                'business_id' => $business_id,
                'imported_by' => $user_id,
                'file_name' => $stored_name,
                'conflict_mode' => $conflict_mode,
                'user_conflict' => $user_conflict,
                'status' => 'failed',
                'error_message' => null,
                'summary_json' => null,
                'imported_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4) Read & validate SQL file
            $sql = Storage::disk('local')->get($stored_path);
            $statements = $this->splitSqlStatements($sql);
            $safe_statements = $this->validateStatementsAndTransform($statements, $conflict_mode);

            // 5) Import inside transaction where possible
            DB::beginTransaction();
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $summary = $this->runSafeImportStatements($safe_statements, $conflict_mode, $user_conflict, (int) $business_id);
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                DB::commit();
            } catch (\Exception $e) {
                // Best effort to re-enable FK checks
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } catch (\Exception $ignored) {
                }
                DB::rollBack();
                throw $e;
            }

            DB::table('custom_backup_import_histories')
                ->where('id', $history_id)
                ->update([
                    'status' => 'success',
                    'error_message' => null,
                    'summary_json' => json_encode($summary),
                    'updated_at' => now(),
                ]);

            $msg = 'Import completed successfully. Backup created: ' . basename($backup_path);
            if (! empty($summary)) {
                $msg .= ' | Summary: users imported ' . ($summary['users_imported'] ?? 0)
                    . ', skipped ' . ($summary['users_skipped'] ?? 0)
                    . ', failed ' . ($summary['users_failed'] ?? 0)
                    . '; roles ' . ($summary['roles_imported'] ?? 0)
                    . '; permissions ' . ($summary['permissions_imported'] ?? 0);
            }
            $output = ['success' => 1, 'msg' => $msg];
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            if (! empty($history_id)) {
                DB::table('custom_backup_import_histories')
                    ->where('id', $history_id)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $error_message,
                        'updated_at' => now(),
                    ]);
            }

            $output = ['success' => 0, 'msg' => $error_message];
        }

        return redirect()->back()->with('status', $output);
    }

    private function authorizeAccess(): void
    {
        $is_admin = $this->commonUtil->is_admin(auth()->user());
        $is_superadmin = auth()->user()->can('superadmin');

        if (! $is_admin && ! $is_superadmin) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function exportProducts($out, int $business_id, Carbon $from, Carbon $to): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $this->writeLine($out, "");
        $this->writeLine($out, "-- Products");

        $product_ids = DB::table('products')
            ->where('business_id', $business_id)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id')
            ->toArray();

        if (empty($product_ids)) {
            $this->writeLine($out, "-- (no rows)");
            return;
        }

        $this->dumpTable($out, 'products', DB::table('products')->whereIn('id', $product_ids));

        if (Schema::hasTable('product_variations')) {
            $this->dumpTable($out, 'product_variations', DB::table('product_variations')->whereIn('product_id', $product_ids));
        }
        if (Schema::hasTable('variations')) {
            $this->dumpTable($out, 'variations', DB::table('variations')->whereIn('product_id', $product_ids));
        }
        if (Schema::hasTable('variation_location_details')) {
            $this->dumpTable($out, 'variation_location_details', DB::table('variation_location_details')->whereIn('product_id', $product_ids));
        }
    }

    private function exportRepair($out, int $business_id, Carbon $from, Carbon $to): void
    {
        $tables = [
            'repair_device_models',
            'repair_job_sheets',
            'repair_statuses',
            'repair_brands',
        ];

        $this->writeLine($out, "");
        $this->writeLine($out, "-- Repair");

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);

            if (Schema::hasColumn($table, 'business_id')) {
                $query->where('business_id', $business_id);
            }

            $date_col = $this->pickDateColumn($table, ['transaction_date', 'created_at', 'updated_at']);
            if (! empty($date_col)) {
                $query->whereBetween($date_col, [$from, $to]);
            }

            $this->dumpTable($out, $table, $query);
        }
    }

    private function exportStockTransfers($out, int $business_id, Carbon $from, Carbon $to): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        $this->writeLine($out, "");
        $this->writeLine($out, "-- Stock Transfers (sell_transfer + purchase_transfer)");

        $parent_ids = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'sell_transfer')
            ->whereBetween('transaction_date', [$from, $to])
            ->pluck('id')
            ->toArray();

        if (empty($parent_ids)) {
            $this->writeLine($out, "-- (no rows)");
            return;
        }

        $child_ids = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'purchase_transfer')
            ->whereIn('transfer_parent_id', $parent_ids)
            ->pluck('id')
            ->toArray();

        $all_ids = array_values(array_unique(array_merge($parent_ids, $child_ids)));

        $this->dumpTable($out, 'transactions', DB::table('transactions')->whereIn('id', $all_ids));

        if (Schema::hasTable('transaction_sell_lines')) {
            $this->dumpTable($out, 'transaction_sell_lines', DB::table('transaction_sell_lines')->whereIn('transaction_id', $all_ids));
        }
        if (Schema::hasTable('transaction_payments')) {
            $this->dumpTable($out, 'transaction_payments', DB::table('transaction_payments')->whereIn('transaction_id', $all_ids));
        }
    }

    private function exportTransactionsByType($out, int $business_id, Carbon $from, Carbon $to, string $type): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        $this->writeLine($out, "");
        $this->writeLine($out, "-- Transactions: {$type}");

        $tx_query = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', $type)
            ->whereBetween('transaction_date', [$from, $to]);

        if ($type === 'sell') {
            $tx_query->where('status', 'final');
        }

        $transaction_ids = $tx_query->pluck('id')->toArray();
        if (empty($transaction_ids)) {
            $this->writeLine($out, "-- (no rows)");
            return;
        }

        $this->dumpTable($out, 'transactions', DB::table('transactions')->whereIn('id', $transaction_ids));

        if ($type === 'purchase' && Schema::hasTable('purchase_lines')) {
            $this->dumpTable($out, 'purchase_lines', DB::table('purchase_lines')->whereIn('transaction_id', $transaction_ids));
        }

        if ($type === 'sell' && Schema::hasTable('transaction_sell_lines')) {
            $this->dumpTable($out, 'transaction_sell_lines', DB::table('transaction_sell_lines')->whereIn('transaction_id', $transaction_ids));
        }

        if ($type === 'stock_adjustment' && Schema::hasTable('stock_adjustment_lines')) {
            $this->dumpTable($out, 'stock_adjustment_lines', DB::table('stock_adjustment_lines')->whereIn('transaction_id', $transaction_ids));
        }

        if (Schema::hasTable('transaction_payments')) {
            $this->dumpTable($out, 'transaction_payments', DB::table('transaction_payments')->whereIn('transaction_id', $transaction_ids));
        }
    }

    private function exportUsersPermissions($out, int $business_id, Carbon $from, Carbon $to): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $this->writeLine($out, "");
        $this->writeLine($out, "-- Users & Permissions");

        $users_query = DB::table('users')->where('business_id', $business_id);
        $date_col = $this->pickDateColumn('users', ['created_at', 'updated_at']);
        if (! empty($date_col)) {
            $users_query->whereBetween($date_col, [$from, $to]);
        }

        $user_ids = $users_query->pluck('id')->toArray();
        if (empty($user_ids)) {
            $this->writeLine($out, "-- (no rows)");
            return;
        }

        $this->dumpTable($out, 'users', DB::table('users')->whereIn('id', $user_ids));

        $tableNames = config('permission.table_names');
        $roles_table = $tableNames['roles'] ?? 'roles';
        $permissions_table = $tableNames['permissions'] ?? 'permissions';
        $model_has_roles = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $model_has_permissions = $tableNames['model_has_permissions'] ?? 'model_has_permissions';
        $role_has_permissions = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        if (! Schema::hasTable($roles_table) || ! Schema::hasTable($permissions_table)) {
            return;
        }

        $model_type = \App\User::class;

        $role_ids = [];
        if (Schema::hasTable($model_has_roles)) {
            $role_ids = DB::table($model_has_roles)
                ->where('model_type', $model_type)
                ->whereIn('model_id', $user_ids)
                ->pluck('role_id')
                ->unique()
                ->values()
                ->toArray();

            $this->dumpTable($out, $model_has_roles, DB::table($model_has_roles)->where('model_type', $model_type)->whereIn('model_id', $user_ids));
        }

        if (! empty($role_ids)) {
            $this->dumpTable($out, $roles_table, DB::table($roles_table)->where('business_id', $business_id)->whereIn('id', $role_ids));
        }

        $permission_ids = [];
        if (Schema::hasTable($role_has_permissions) && ! empty($role_ids)) {
            $permission_ids = array_merge($permission_ids, DB::table($role_has_permissions)->whereIn('role_id', $role_ids)->pluck('permission_id')->toArray());
            $this->dumpTable($out, $role_has_permissions, DB::table($role_has_permissions)->whereIn('role_id', $role_ids));
        }

        if (Schema::hasTable($model_has_permissions)) {
            $permission_ids = array_merge(
                $permission_ids,
                DB::table($model_has_permissions)
                    ->where('model_type', $model_type)
                    ->whereIn('model_id', $user_ids)
                    ->pluck('permission_id')
                    ->toArray()
            );
            $this->dumpTable($out, $model_has_permissions, DB::table($model_has_permissions)->where('model_type', $model_type)->whereIn('model_id', $user_ids));
        }

        $permission_ids = array_values(array_unique(array_filter($permission_ids)));
        if (! empty($permission_ids)) {
            $this->dumpTable($out, $permissions_table, DB::table($permissions_table)->whereIn('id', $permission_ids));
        }
    }

    private function dumpTable($out, string $table, $query, int $chunkSize = 500): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = Schema::getColumnListing($table);
        if (empty($columns)) {
            return;
        }

        $query = $query->select($columns);

        $this->writeLine($out, "");
        $this->writeLine($out, "-- Table: {$table}");

        $has_rows = false;

        $query->orderBy($columns[0])
            ->chunk($chunkSize, function ($rows) use ($out, $table, $columns, &$has_rows) {
                if ($rows->isEmpty()) {
                    return;
                }

                $has_rows = true;
                $col_sql = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));

                $values_sql = [];
                foreach ($rows as $row) {
                    $row_values = [];
                    foreach ($columns as $col) {
                        $row_values[] = $this->sqlValue($row->{$col} ?? null);
                    }
                    $values_sql[] = '(' . implode(', ', $row_values) . ')';
                }

                $this->writeLine($out, "INSERT INTO `{$table}` ({$col_sql}) VALUES");
                $this->writeLine($out, implode(",\n", $values_sql) . ';');
            });

        if (! $has_rows) {
            $this->writeLine($out, "-- (no rows)");
        }
    }

    private function sqlValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Numeric strings should remain quoted to preserve leading zeros etc.
        $pdo = DB::connection()->getPdo();
        return $pdo->quote((string) $value);
    }

    private function pickDateColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        return null;
    }

    private function writeLine($out, string $line): void
    {
        fwrite($out, $line . "\n");
    }

    private function createPreImportBackup(?int $business_id = null): string
    {
        // Store backup zip in storage/app/backups (local) as requested
        $backup_dir = storage_path('app/backups');
        if (! is_dir($backup_dir)) {
            @mkdir($backup_dir, 0755, true);
        }

        // Run spatie/laravel-backup database-only backup (doesn't modify existing backup system)
        Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        $disk_name = config('backup.backup.destination.disks')[0] ?? 'local';
        $backup_name = config('backup.backup.name');
        $disk = Storage::disk($disk_name);

        $files = $disk->files($backup_name);
        $zip_files = array_values(array_filter($files, fn ($f) => str_ends_with($f, '.zip')));
        if (empty($zip_files)) {
            throw new \Exception('Pre-import backup failed: could not find backup zip file.');
        }

        // Pick latest zip by lastModified
        usort($zip_files, function ($a, $b) use ($disk) {
            return $disk->lastModified($b) <=> $disk->lastModified($a);
        });
        $latest = $zip_files[0];

        $target = $backup_dir . '/pre_import_' . now()->format('Ymd_His') . '_' . basename($latest);
        $stream = $disk->readStream($latest);
        if (! $stream) {
            throw new \Exception('Pre-import backup failed: unable to read backup stream.');
        }
        $dest = fopen($target, 'w');
        stream_copy_to_stream($stream, $dest);
        fclose($dest);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $target;
    }

    /**
     * Split SQL into statements while respecting quoted strings.
     *
     * @param  string  $sql
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $in_single = false;
        $in_double = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            // Handle line comments starting with --
            if (! $in_single && ! $in_double && $ch === '-' && $next === '-') {
                // consume until newline
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // Handle block comments /* ... */
            if (! $in_single && ! $in_double && $ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++; // skip '/'
                continue;
            }

            if ($ch === "'" && ! $in_double) {
                // check escape
                $escaped = ($i > 0 && $sql[$i - 1] === '\\');
                if (! $escaped) {
                    $in_single = ! $in_single;
                }
                $buffer .= $ch;
                continue;
            }

            if ($ch === '"' && ! $in_single) {
                $escaped = ($i > 0 && $sql[$i - 1] === '\\');
                if (! $escaped) {
                    $in_double = ! $in_double;
                }
                $buffer .= $ch;
                continue;
            }

            if (! $in_single && ! $in_double && $ch === ';') {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt . ';';
                }
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * Validate statements against allowlist and transform INSERT mode.
     *
     * @param  array<int, string>  $statements
     * @return array<int, string>
     */
    private function validateStatementsAndTransform(array $statements, string $conflict_mode): array
    {
        $out = [];
        foreach ($statements as $stmt) {
            $trim = trim($stmt);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }

            $upper = strtoupper($this->stripSqlStringLiterals($trim));

            // Block dangerous commands anywhere
            $forbidden = [
                'DROP DATABASE',
                'DROP TABLE',
                'TRUNCATE',
                'ALTER USER',
                'CREATE USER',
                'GRANT ',
                'REVOKE ',
                'LOAD DATA',
                'INTO OUTFILE',
            ];
            foreach ($forbidden as $bad) {
                if (str_contains($upper, $bad)) {
                    throw new \Exception("Blocked dangerous SQL statement: {$bad}");
                }
            }

            // Only allow SET FOREIGN_KEY_CHECKS and INSERT/REPLACE statements
            if (preg_match('/^SET\\s+FOREIGN_KEY_CHECKS\\s*=\\s*[01]\\s*;?$/i', $trim)) {
                $out[] = rtrim($trim, ';') . ';';
                continue;
            }

            if (preg_match('/^INSERT\\s+INTO\\s+/i', $trim)) {
                if ($conflict_mode === 'ignore') {
                    $trim = preg_replace('/^INSERT\\s+INTO\\s+/i', 'INSERT IGNORE INTO ', $trim, 1);
                } elseif ($conflict_mode === 'replace') {
                    $trim = preg_replace('/^INSERT\\s+INTO\\s+/i', 'REPLACE INTO ', $trim, 1);
                }
                $out[] = rtrim($trim, ';') . ';';
                continue;
            }

            if (preg_match('/^REPLACE\\s+INTO\\s+/i', $trim)) {
                // allow replace only if user selected replace
                if ($conflict_mode !== 'replace') {
                    throw new \Exception('REPLACE statements are only allowed in "Replace existing" mode.');
                }
                $out[] = rtrim($trim, ';') . ';';
                continue;
            }

            // Explicitly disallow DELETE/UPDATE/ALTER/CREATE/etc
            throw new \Exception('Blocked unsupported SQL statement. Only INSERT and SET FOREIGN_KEY_CHECKS are allowed.');
        }

        return $out;
    }

    /**
     * Removes quoted string literals from SQL to avoid false-positive keyword blocking.
     */
    private function stripSqlStringLiterals(string $sql): string
    {
        $out = '';
        $len = strlen($sql);
        $in_single = false;
        $in_double = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($in_single) {
                if ($ch === "\\" && $i + 1 < $len) {
                    $i++;
                    continue;
                }
                if ($ch === "'") {
                    $in_single = false;
                }
                continue;
            }
            if ($in_double) {
                if ($ch === "\\" && $i + 1 < $len) {
                    $i++;
                    continue;
                }
                if ($ch === '"') {
                    $in_double = false;
                }
                continue;
            }
            if ($ch === "'") {
                $in_single = true;
                continue;
            }
            if ($ch === '"') {
                $in_double = true;
                continue;
            }
            $out .= $ch;
        }

        return $out;
    }

    /**
     * Execute safe statements, with special handling for Users & Permissions.
     *
     * @param  array<int, string>  $safe_statements
     * @return array<string, int>
     */
    private function runSafeImportStatements(array $safe_statements, string $conflict_mode, string $user_conflict, int $business_id): array
    {
        $summary = [
            'users_imported' => 0,
            'users_skipped' => 0,
            'users_failed' => 0,
            'roles_imported' => 0,
            'permissions_imported' => 0,
        ];

        $tableNames = config('permission.table_names');
        $roles_table = $tableNames['roles'] ?? 'roles';
        $permissions_table = $tableNames['permissions'] ?? 'permissions';
        $model_has_roles = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $model_has_permissions = $tableNames['model_has_permissions'] ?? 'model_has_permissions';
        $role_has_permissions = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        $model_type = \App\User::class;
        $user_id_map = []; // old_id => new_id

        foreach ($safe_statements as $stmt) {
            $trim = trim($stmt);
            if ($trim === '') {
                continue;
            }

            if (preg_match('/^SET\\s+FOREIGN_KEY_CHECKS\\s*=\\s*[01]\\s*;?$/i', $trim)) {
                DB::statement(rtrim($trim, ';') . ';');
                continue;
            }

            $table = $this->extractInsertTableName($trim);
            if (empty($table)) {
                DB::unprepared($trim);
                continue;
            }

            if ($table === 'users') {
                $result = $this->importUsersFromInsert($trim, $user_conflict, $business_id);
                $summary['users_imported'] += $result['imported'];
                $summary['users_skipped'] += $result['skipped'];
                $summary['users_failed'] += $result['failed'];
                $user_id_map = $user_id_map + ($result['id_map'] ?? []);
                continue;
            }

            if ($table === $roles_table) {
                $summary['roles_imported'] += $this->executeInsertLike($trim);
                continue;
            }

            if ($table === $permissions_table) {
                $summary['permissions_imported'] += $this->executeInsertLike($trim);
                continue;
            }

            if (in_array($table, [$model_has_roles, $model_has_permissions], true) && ! empty($user_id_map)) {
                $mapped = $this->rewriteModelIdInPivotInsert($trim, $user_id_map, $model_type);
                $this->executeInsertLike($mapped);
                continue;
            }

            if ($table === $role_has_permissions) {
                $this->executeInsertLike($trim);
                continue;
            }

            DB::unprepared($trim);
        }

        return $summary;
    }

    private function extractInsertTableName(string $sql): ?string
    {
        if (! preg_match('/^(INSERT|REPLACE)\\s+(IGNORE\\s+)?INTO\\s+`?([A-Za-z0-9_]+)`?/i', ltrim($sql), $m)) {
            return null;
        }

        return $m[3] ?? null;
    }

    /**
     * Execute an INSERT/REPLACE statement and return number of value rows (best-effort).
     */
    private function executeInsertLike(string $sql): int
    {
        $parsed = $this->parseInsertStatement($sql);
        DB::unprepared(rtrim($sql, ';') . ';');
        return count($parsed['rows'] ?? []);
    }

    /**
     * Parse exporter-generated INSERT/REPLACE statement into table/columns/rows.
     *
     * @return array{table:string,columns:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    private function parseInsertStatement(string $sql): array
    {
        $sql = trim(rtrim($sql, ';')) . ';';
        if (! preg_match('/^(INSERT|REPLACE)\\s+(IGNORE\\s+)?INTO\\s+`?([A-Za-z0-9_]+)`?\\s*\\((.*?)\\)\\s*VALUES\\s*(.*);$/is', $sql, $m)) {
            throw new \Exception('Unsupported INSERT format for custom import.');
        }

        $table = $m[3];
        $columns_raw = trim($m[4]);
        $values_raw = trim($m[5]);

        $columns = array_values(array_filter(array_map(function ($c) {
            $c = trim($c);
            $c = trim($c, "` \t\n\r\0\x0B");
            return $c;
        }, explode(',', $columns_raw))));

        $rows = $this->parseValuesTuples($values_raw);

        return ['table' => $table, 'columns' => $columns, 'rows' => $rows];
    }

    /**
     * Parse VALUES tuples like: (1,'a'),(2,NULL)
     *
     * @return array<int,array<int,mixed>>
     */
    private function parseValuesTuples(string $values_raw): array
    {
        $rows = [];
        $len = strlen($values_raw);
        $i = 0;
        while ($i < $len) {
            while ($i < $len && ctype_space($values_raw[$i])) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            if ($values_raw[$i] !== '(') {
                $i++;
                continue;
            }
            $i++; // skip '('
            $row = [];
            $value = '';
            $in_single = false;
            $in_double = false;
            $was_quoted = false;

            while ($i < $len) {
                $ch = $values_raw[$i];
                if ($in_single) {
                    if ($ch === "\\" && $i + 1 < $len) {
                        $value .= $values_raw[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($ch === "'") {
                        $in_single = false;
                        $was_quoted = true;
                        $i++;
                        continue;
                    }
                    $value .= $ch;
                    $i++;
                    continue;
                }
                if ($in_double) {
                    if ($ch === "\\" && $i + 1 < $len) {
                        $value .= $values_raw[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($ch === '"') {
                        $in_double = false;
                        $was_quoted = true;
                        $i++;
                        continue;
                    }
                    $value .= $ch;
                    $i++;
                    continue;
                }

                if ($ch === "'") {
                    $in_single = true;
                    $i++;
                    continue;
                }
                if ($ch === '"') {
                    $in_double = true;
                    $i++;
                    continue;
                }

                if ($ch === ',') {
                    $row[] = $this->coerceSqlLiteral($value, $was_quoted);
                    $value = '';
                    $was_quoted = false;
                    $i++;
                    continue;
                }

                if ($ch === ')') {
                    $row[] = $this->coerceSqlLiteral($value, $was_quoted);
                    $i++; // skip ')'
                    break;
                }

                $value .= $ch;
                $i++;
            }

            $rows[] = $row;
            while ($i < $len && $values_raw[$i] !== '(') {
                $i++;
            }
        }

        return $rows;
    }

    private function coerceSqlLiteral(string $raw, bool $was_quoted)
    {
        $t = trim($raw);
        if (! $was_quoted) {
            if (strcasecmp($t, 'NULL') === 0 || $t === '') {
                return null;
            }
            if (is_numeric($t)) {
                return $t + 0;
            }
        }

        return $t;
    }

    /**
     * Import users with email-based conflict handling and optional ID remapping.
     *
     * @return array{imported:int,skipped:int,failed:int,id_map:array<int,int>}
     */
    private function importUsersFromInsert(string $sql, string $user_conflict, int $business_id): array
    {
        $parsed = $this->parseInsertStatement($sql);
        $columns = $parsed['columns'];

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $id_map = [];

        foreach ($parsed['rows'] as $row) {
            $data = [];
            foreach ($columns as $idx => $col) {
                $data[$col] = $row[$idx] ?? null;
            }

            // Security: import only selected business users
            if (array_key_exists('business_id', $data) && (int) $data['business_id'] !== (int) $business_id) {
                $skipped++;
                continue;
            }

            $old_id = isset($data['id']) ? (int) $data['id'] : null;
            $email = isset($data['email']) ? (string) $data['email'] : null;
            $password_hash = $data['password'] ?? null;

            $existing_by_email = null;
            if (! empty($email)) {
                $existing_by_email = DB::table('users')
                    ->where('business_id', $business_id)
                    ->where('email', $email)
                    ->first();
            }

            try {
                if ($existing_by_email) {
                    if ($user_conflict === 'skip') {
                        $skipped++;
                        if (! empty($old_id)) {
                            $id_map[$old_id] = (int) $existing_by_email->id;
                        }
                        continue;
                    }

                    $update = $data;
                    unset($update['id']);

                    if ($user_conflict === 'update') {
                        // Never reset password in update mode
                        unset($update['password']);
                    }

                    // Preserve business_id
                    $update['business_id'] = $business_id;

                    DB::table('users')->where('id', $existing_by_email->id)->update($update);
                    $imported++;
                    if (! empty($old_id)) {
                        $id_map[$old_id] = (int) $existing_by_email->id;
                    }
                    continue;
                }

                // No existing by email; try to keep ID if free
                if (! empty($old_id)) {
                    $exists_id = DB::table('users')->where('id', $old_id)->exists();
                    if ($exists_id) {
                        // ID conflict: insert without id and map to new ID
                        $insert = $data;
                        unset($insert['id']);
                        $insert['business_id'] = $business_id;
                        $new_id = DB::table('users')->insertGetId($insert);
                        $id_map[$old_id] = (int) $new_id;
                        $imported++;
                        continue;
                    }
                }

                $insert = $data;
                $insert['business_id'] = $business_id;
                if ($user_conflict !== 'replace') {
                    // keep hash but don't auto-change; still safe since it's hashed
                    $insert['password'] = $password_hash;
                }
                DB::table('users')->insert($insert);
                $imported++;
                if (! empty($old_id)) {
                    $id_map[$old_id] = $old_id;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'id_map' => $id_map,
        ];
    }

    /**
     * Rewrite model_id in model_has_roles/model_has_permissions inserts when user IDs were remapped.
     */
    private function rewriteModelIdInPivotInsert(string $sql, array $user_id_map, string $model_type): string
    {
        $parsed = $this->parseInsertStatement($sql);
        $table = $parsed['table'];
        $columns = $parsed['columns'];
        $rows = $parsed['rows'];

        $model_id_idx = array_search('model_id', $columns, true);
        $model_type_idx = array_search('model_type', $columns, true);

        if ($model_id_idx === false || $model_type_idx === false) {
            return $sql;
        }

        foreach ($rows as $r_idx => $row) {
            $mt = $row[$model_type_idx] ?? null;
            if ($mt !== $model_type) {
                continue;
            }
            $mid = isset($row[$model_id_idx]) ? (int) $row[$model_id_idx] : null;
            if (! empty($mid) && isset($user_id_map[$mid])) {
                $rows[$r_idx][$model_id_idx] = (int) $user_id_map[$mid];
            }
        }

        $col_sql = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
        $values_sql = [];
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $v) {
                $vals[] = $this->sqlValue($v);
            }
            $values_sql[] = '(' . implode(', ', $vals) . ')';
        }

        return "INSERT INTO `{$table}` ({$col_sql}) VALUES\n" . implode(",\n", $values_sql) . ';';
    }
}
