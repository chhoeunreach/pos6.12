<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanRoleController extends Controller
{
    public function index()
    {
        $this->authorizeRoleAccess('roles.view');
        $this->ensurePermissionTables();

        $baseQuery = Role::query()
            ->when(Schema::hasColumn('roles', 'business_id'), fn ($query) => $query->where('business_id', $this->businessId()));

        $roleIds = (clone $baseQuery)->pluck('id');
        $stats = [
            'total' => $roleIds->count(),
            'assigned' => Schema::hasTable('model_has_roles') && $roleIds->isNotEmpty()
                ? DB::table('model_has_roles')->whereIn('role_id', $roleIds)->count()
                : 0,
            'permissions' => Schema::hasTable('permissions') ? Permission::where('guard_name', 'web')->count() : 0,
        ];

        $roles = (clone $baseQuery)
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->paginate(20);

        return view('loanmanagement::roles.index', compact('roles', 'stats'));
    }

    public function create()
    {
        $this->authorizeRoleAccess('roles.create');
        $this->ensurePermissionTables();

        return view('loanmanagement::roles.create', [
            'permissionGroups' => $this->permissionGroups(),
            'selectedPermissions' => [],
            'businessId' => $this->businessId(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeRoleAccess('roles.create');
        $this->ensurePermissionTables();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('roles', 'name')->where(function ($query) {
                    $query->where('guard_name', 'web');
                    if (Schema::hasColumn('roles', 'business_id')) {
                        $query->where('business_id', $this->businessId());
                    }
                }),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $payload = [
            'name' => trim($data['name']),
            'guard_name' => 'web',
        ];

        if (Schema::hasColumn('roles', 'business_id')) {
            $payload['business_id'] = $this->businessId();
        }

        $role = Role::create($payload);

        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('loan-management.roles.index')
            ->with('status', ['success' => 1, 'msg' => 'Role created successfully.']);
    }

    public function edit(Role $role)
    {
        $this->authorizeRoleAccess('roles.update');
        $this->abortIfOutsideBusiness($role);

        return view('loanmanagement::roles.edit', [
            'role' => $role->load('permissions'),
            'permissionGroups' => $this->permissionGroups(),
            'selectedPermissions' => $role->permissions->pluck('name')->all(),
            'businessId' => $this->businessId(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeRoleAccess('roles.update');
        $this->abortIfOutsideBusiness($role);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('roles', 'name')->ignore($role->id)->where(function ($query) {
                    $query->where('guard_name', 'web');
                    if (Schema::hasColumn('roles', 'business_id')) {
                        $query->where('business_id', $this->businessId());
                    }
                }),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update(['name' => trim($data['name'])]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('loan-management.roles.index')
            ->with('status', ['success' => 1, 'msg' => 'Role updated successfully.']);
    }

    public function destroy(Role $role)
    {
        $this->authorizeRoleAccess('roles.delete');
        $this->abortIfOutsideBusiness($role);

        abort_if($role->users()->exists(), 422, 'This role is assigned to users and cannot be deleted.');

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('loan-management.roles.index')
            ->with('status', ['success' => 1, 'msg' => 'Role deleted successfully.']);
    }

    public function export()
    {
        $this->authorizeRoleAccess('roles.view');
        $this->ensurePermissionTables();

        $roles = Role::query()
            ->with('permissions')
            ->when(Schema::hasColumn('roles', 'business_id'), fn ($query) => $query->where('business_id', $this->businessId()))
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($roles) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['name', 'guard_name', 'business_id', 'permissions']);

            foreach ($roles as $role) {
                fputcsv($output, [
                    $role->name,
                    $role->guard_name,
                    $role->business_id ?? $this->businessId(),
                    $role->permissions->pluck('name')->implode('|'),
                ]);
            }

            fclose($output);
        }, 'roles-export-'.date('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function downloadTemplate()
    {
        $this->authorizeRoleAccess('roles.create');
        $this->ensurePermissionTables();

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['name', 'guard_name', 'business_id', 'permissions']);
            fputcsv($output, ['Loan Staff', 'web', $this->businessId(), 'loan_management.view|loan_management.loans.view|loan_management.payments.view']);
            fclose($output);
        }, 'roles-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $this->authorizeRoleAccess('roles.create');
        $this->ensurePermissionTables();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'mode' => 'required|in:insert,update,upsert',
        ]);

        $rows = $this->csvRows($request->file('file')->getRealPath());
        if (empty($rows)) {
            return back()->withErrors(['file' => 'The import file is empty or missing headers.']);
        }

        $mode = $request->input('mode', 'insert');
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $mode, &$imported, &$updated, &$skipped, &$errors) {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    $skipped++;
                    $errors[] = 'Row '.$line.': name is required.';
                    continue;
                }

                $existing = Role::query()
                    ->where('name', $name)
                    ->where('guard_name', 'web')
                    ->when(Schema::hasColumn('roles', 'business_id'), fn ($query) => $query->where('business_id', $this->businessId()))
                    ->first();

                if ($existing && $mode === 'insert') {
                    $skipped++;
                    continue;
                }

                if (! $existing && $mode === 'update') {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'name' => $name,
                    'guard_name' => 'web',
                ];

                if (Schema::hasColumn('roles', 'business_id')) {
                    $payload['business_id'] = (int) ($row['business_id'] ?? $this->businessId()) ?: $this->businessId();
                }

                $role = $existing ?: Role::create($payload);
                if ($existing) {
                    $role->update($payload);
                }

                $permissionNames = $this->permissionNamesFromCsv((string) ($row['permissions'] ?? ''));
                $permissions = collect($permissionNames)->map(function ($permissionName) {
                    return Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
                })->all();

                $role->syncPermissions($permissions);
                $existing ? $updated++ : $imported++;
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $message = 'Role import completed. Imported: '.$imported.', Updated: '.$updated.', Skipped: '.$skipped.'.';
        if (! empty($errors)) {
            $message .= ' '.count($errors).' row issue(s): '.implode(' ', array_slice($errors, 0, 3));
        }

        return redirect()->route('loan-management.roles.index')->with('status', ['success' => empty($errors) ? 1 : 0, 'msg' => $message]);
    }

    protected function authorizeRoleAccess(string $permission): void
    {
        abort_unless(auth()->check() && auth()->user()->can($permission), 403, 'Unauthorized action.');
    }

    protected function ensurePermissionTables(): void
    {
        abort_unless(Schema::hasTable('roles') && Schema::hasTable('permissions'), 500, 'Permission tables are not installed.');
    }

    protected function permissionGroups()
    {
        foreach ((array) config('loanmanagement.permissions', []) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(function ($permission) {
                $permission->group_label = $this->permissionGroupLabel((string) $permission->name);
                $permission->display_label = $this->permissionDisplayLabel((string) $permission->name);

                return $permission;
            })
            ->groupBy('group_label')
            ->sortBy(function ($permissions, $label) {
                $order = [
                    'Others',
                    'User',
                    'Roles',
                    'Supplier',
                    'Customer',
                    'Loan Management',
                    'Loan',
                    'Payment',
                    'Collection',
                    'Chat',
                    'Reports',
                    'Settings',
                ];
                $position = array_search($label, $order, true);

                return $position === false ? 999 : $position;
            });
    }

    protected function permissionGroupLabel(string $name): string
    {
        if (str_starts_with($name, 'supplier.')) {
            return 'Supplier';
        }

        if (str_starts_with($name, 'customer.')) {
            return 'Customer';
        }

        if (str_starts_with($name, 'user.')) {
            return 'User';
        }

        if (str_starts_with($name, 'roles.')) {
            return 'Roles';
        }

        if (str_starts_with($name, 'loan_management.customers.')) {
            return 'Customer';
        }

        if (str_starts_with($name, 'loan_management.loans.')) {
            return 'Loan';
        }

        if (str_starts_with($name, 'loan_management.payments.')) {
            return 'Payment';
        }

        if (str_starts_with($name, 'loan_management.chat.')) {
            return 'Chat';
        }

        if (str_starts_with($name, 'loan_management.collection.')) {
            return 'Collection';
        }

        if (str_starts_with($name, 'loan_management.reports.')) {
            return 'Reports';
        }

        if (str_starts_with($name, 'loan_management.settings.') || $name === 'loan_management.setting') {
            return 'Settings';
        }

        if (str_starts_with($name, 'loan_management.')) {
            return 'Loan Management';
        }

        return 'Others';
    }

    protected function permissionDisplayLabel(string $name): string
    {
        $labels = [
            'access_all_locations' => 'Access all locations',
            'view_export_buttons' => 'View export buttons on tables',
            'user.view' => 'View user',
            'user.create' => 'Add user',
            'user.update' => 'Edit user',
            'user.delete' => 'Delete user',
            'roles.view' => 'View role',
            'roles.create' => 'Add Role',
            'roles.update' => 'Edit Role',
            'roles.delete' => 'Delete role',
            'loan_management.view' => 'View loan management',
            'loan_management.create' => 'Add loan management record',
            'loan_management.edit' => 'Edit loan management record',
            'loan_management.delete' => 'Delete loan management record',
            'loan_management.setting' => 'Manage loan settings',
        ];

        if (isset($labels[$name])) {
            return $labels[$name];
        }

        $parts = explode('.', $name);
        $action = array_pop($parts);
        $subject = array_pop($parts) ?: $name;
        $actionLabels = [
            'view' => 'View',
            'create' => 'Add',
            'edit' => 'Edit',
            'update' => 'Edit',
            'delete' => 'Delete',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'reply' => 'Reply',
            'assign' => 'Assign',
            'transfer' => 'Transfer',
            'close' => 'Close',
            'admin' => 'Admin access',
            'manage' => 'Manage',
        ];

        $subject = str_replace('_', ' ', $subject);
        $actionText = $actionLabels[$action] ?? ucfirst(str_replace('_', ' ', $action));

        return trim($actionText.' '.$subject);
    }

    protected function businessId(): int
    {
        return (int) (session('user.business_id') ?? auth()->user()->business_id ?? 1);
    }

    protected function abortIfOutsideBusiness(Role $role): void
    {
        if (Schema::hasColumn('roles', 'business_id')) {
            abort_unless((int) $role->business_id === $this->businessId(), 404);
        }
    }

    protected function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_slice(array_pad($data, count($headers), ''), 0, count($headers)));
        }

        fclose($handle);

        return $rows;
    }

    protected function permissionNamesFromCsv(string $permissions): array
    {
        return collect(preg_split('/[|,]/', $permissions) ?: [])
            ->map(fn ($permission) => trim((string) $permission))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
