<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class LoanUserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeUserAccess('user.view');

        $hasRoleTables = Schema::hasTable('roles') && Schema::hasTable('model_has_roles');
        $baseQuery = User::query()
            ->when($hasRoleTables, fn ($q) => $q->with('roles'))
            ->when(Schema::hasColumn('users', 'business_id'), fn ($q) => $q->where('business_id', $this->businessId()));

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => Schema::hasColumn('users', 'status') ? (clone $baseQuery)->where('status', 'active')->count() : 0,
            'inactive' => Schema::hasColumn('users', 'status') ? (clone $baseQuery)->where('status', 'inactive')->count() : 0,
            'login_enabled' => Schema::hasColumn('users', 'allow_login') ? (clone $baseQuery)->where('allow_login', 1)->count() : 0,
        ];

        $query = (clone $baseQuery)->orderByDesc('id');

        foreach (['name', 'username', 'email', 'status'] as $field) {
            if ($request->filled($field)) {
                $value = $request->input($field);
                $field === 'status'
                    ? $query->where($field, $value)
                    : $query->where($field, 'like', '%'.$value.'%');
            }
        }

        if ($hasRoleTables && $request->filled('role')) {
            $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey((int) $request->input('role')));
        }

        $perPage = (int) $request->input('per_page', 250);
        if ($perPage <= 0 || $perPage > 1000) {
            $perPage = 250;
        }

        $users = $query->paginate($perPage)->appends($request->query());
        $roles = $this->rolesForSelect();

        return view('loanmanagement::users.index', compact('users', 'stats', 'roles'));
    }

    public function create()
    {
        $this->authorizeUserAccess('user.create');

        return view('loanmanagement::users.create', [
            'roles' => $this->rolesForSelect(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeUserAccess('user.create');

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'username' => 'required|string|max:191|unique:users,username',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'business_id' => 'nullable|integer|min:1',
            'allow_login' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'role' => 'nullable|integer|exists:roles,id',
        ]);

        $user = User::create([
            'name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name'] ?? ''),
            'username' => trim($data['username']),
            'email' => trim($data['email']),
            'password' => Hash::make($data['password']),
            'business_id' => $data['business_id'] ?? (auth()->user()->business_id ?? 1),
            'allow_login' => $request->boolean('allow_login'),
            'status' => $data['status'],
        ]);

        $this->syncRole($user, $data['role'] ?? null);

        return redirect()->route('loan-management.users.index')
            ->with('status', ['success' => 1, 'msg' => 'User created successfully.']);
    }

    public function show(User $user)
    {
        $this->authorizeUserAccess('user.view');
        $this->abortIfOutsideBusiness($user);

        return view('loanmanagement::users.show', [
            'userRow' => Schema::hasTable('model_has_roles') ? $user->load('roles') : $user,
        ]);
    }

    public function edit(User $user)
    {
        $this->authorizeUserAccess('user.update');
        $this->abortIfOutsideBusiness($user);

        return view('loanmanagement::users.edit', [
            'userRow' => Schema::hasTable('model_has_roles') ? $user->load('roles') : $user,
            'roles' => $this->rolesForSelect(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeUserAccess('user.update');
        $this->abortIfOutsideBusiness($user);

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'username' => 'required|string|max:191|unique:users,username,'.$user->id,
            'email' => 'required|email|max:191|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6|confirmed',
            'business_id' => 'nullable|integer|min:1',
            'allow_login' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'role' => 'nullable|integer|exists:roles,id',
        ]);

        $payload = [
            'name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name'] ?? ''),
            'username' => trim($data['username']),
            'email' => trim($data['email']),
            'business_id' => $data['business_id'] ?? ($user->business_id ?: 1),
            'allow_login' => $request->boolean('allow_login'),
            'status' => $data['status'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $this->syncRole($user, $data['role'] ?? null);

        return redirect()->route('loan-management.users.index')
            ->with('status', ['success' => 1, 'msg' => 'User updated successfully.']);
    }

    public function destroy(User $user)
    {
        $this->authorizeUserAccess('user.delete');
        $this->abortIfOutsideBusiness($user);

        abort_if(auth()->id() === $user->id, 422, 'You cannot delete your own account.');

        $user->delete();

        return redirect()->route('loan-management.users.index')
            ->with('status', ['success' => 1, 'msg' => 'User deleted successfully.']);
    }

    public function toggleStatus(User $user)
    {
        $this->authorizeUserAccess('user.update');
        $this->abortIfOutsideBusiness($user);

        abort_if(auth()->id() === $user->id, 422, 'You cannot disable your own account.');

        $user->update([
            'status' => ($user->status ?? 'active') === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('status', ['success' => 1, 'msg' => 'User status updated.']);
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeUserAccess('user.update');
        $this->abortIfOutsideBusiness($user);

        $data = $request->validate([
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user->update(['password' => Hash::make($data['new_password'])]);

        return back()->with('status', ['success' => 1, 'msg' => 'Password reset successfully.']);
    }

    public function export()
    {
        $this->authorizeUserAccess('user.view');

        $hasRoleTables = Schema::hasTable('roles') && Schema::hasTable('model_has_roles');
        $users = User::query()
            ->when($hasRoleTables, fn ($q) => $q->with('roles'))
            ->when(Schema::hasColumn('users', 'business_id'), fn ($q) => $q->where('business_id', $this->businessId()))
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($users) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['first_name', 'last_name', 'username', 'email', 'status', 'allow_login', 'business_id', 'role']);

            foreach ($users as $user) {
                fputcsv($output, [
                    $user->first_name,
                    $user->last_name,
                    $user->username,
                    $user->email,
                    $user->status ?? 'active',
                    ! empty($user->allow_login) ? 1 : 0,
                    $user->business_id ?? $this->businessId(),
                    $user->relationLoaded('roles') ? $user->roles->pluck('name')->implode('|') : '',
                ]);
            }

            fclose($output);
        }, 'loan-management-users-export-'.date('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function downloadTemplate()
    {
        $this->authorizeUserAccess('user.create');

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['first_name', 'last_name', 'username', 'email', 'password', 'status', 'allow_login', 'business_id', 'role']);
            fputcsv($output, ['Sok', 'Dara', 'sokdara', 'sokdara@example.com', '12345678', 'active', 1, $this->businessId(), 'Admin']);
            fclose($output);
        }, 'loan-management-users-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $this->authorizeUserAccess('user.create');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'mode' => 'required|in:insert,update,upsert',
            'default_password' => 'nullable|string|min:6|max:100',
        ]);

        $rows = $this->csvRows($request->file('file')->getRealPath());
        if (empty($rows)) {
            return back()->withErrors(['file' => 'The import file is empty or missing headers.']);
        }

        $mode = $request->input('mode', 'insert');
        $defaultPassword = $request->input('default_password') ?: '12345678';
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $mode, $defaultPassword, &$imported, &$updated, &$skipped, &$errors) {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $username = trim((string) ($row['username'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                $firstName = trim((string) ($row['first_name'] ?? ''));

                if ($username === '' || $email === '' || $firstName === '') {
                    $skipped++;
                    $errors[] = 'Row '.$line.': first_name, username, and email are required.';
                    continue;
                }

                $existing = User::query()
                    ->when(Schema::hasColumn('users', 'business_id'), fn ($q) => $q->where('business_id', $this->businessId()))
                    ->where(function ($query) use ($username, $email) {
                        $query->where('username', $username)->orWhere('email', $email);
                    })
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
                    'name' => trim($firstName.' '.trim((string) ($row['last_name'] ?? ''))),
                    'first_name' => $firstName,
                    'last_name' => trim((string) ($row['last_name'] ?? '')),
                    'username' => $username,
                    'email' => $email,
                    'business_id' => (int) ($row['business_id'] ?? $this->businessId()) ?: $this->businessId(),
                    'allow_login' => $this->truthy($row['allow_login'] ?? true),
                    'status' => in_array(strtolower(trim((string) ($row['status'] ?? 'active'))), ['active', 'inactive'], true)
                        ? strtolower(trim((string) ($row['status'] ?? 'active')))
                        : 'active',
                ];

                if (! $existing || trim((string) ($row['password'] ?? '')) !== '') {
                    $payload['password'] = Hash::make(trim((string) ($row['password'] ?? '')) ?: $defaultPassword);
                }

                $user = $existing ?: new User();
                $user->fill($payload);
                $user->save();
                $this->syncRoleByName($user, trim((string) ($row['role'] ?? '')));

                $existing ? $updated++ : $imported++;
            }
        });

        $message = 'Import completed. Imported: '.$imported.', Updated: '.$updated.', Skipped: '.$skipped.'.';
        if (! empty($errors)) {
            $message .= ' '.count($errors).' row issue(s): '.implode(' ', array_slice($errors, 0, 3));
        }

        return redirect()->route('loan-management.users.index')->with('status', ['success' => empty($errors) ? 1 : 0, 'msg' => $message]);
    }

    protected function authorizeUserAccess(string $permission): void
    {
        abort_unless(auth()->check() && auth()->user()->can($permission), 403, 'Unauthorized action.');
    }

    protected function rolesForSelect()
    {
        return Schema::hasTable('roles')
            ? Role::query()
                ->when(Schema::hasColumn('roles', 'business_id'), fn ($query) => $query->where('business_id', $this->businessId()))
                ->orderBy('name')
                ->pluck('name', 'id')
            : collect();
    }

    protected function syncRole(User $user, $roleId): void
    {
        if (! Schema::hasTable('model_has_roles')) {
            return;
        }

        if (! $roleId || ! Schema::hasTable('roles')) {
            $user->syncRoles([]);
            return;
        }

        $role = Role::query()
            ->whereKey($roleId)
            ->when(Schema::hasColumn('roles', 'business_id'), fn ($query) => $query->where('business_id', $this->businessId()))
            ->first();

        if ($role) {
            $user->syncRoles([$role]);
        }
    }

    protected function syncRoleByName(User $user, string $roleName): void
    {
        if ($roleName === '' || ! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $roleName = trim(explode('|', $roleName)[0]);
        $role = Role::query()
            ->where('name', $roleName)
            ->when(Schema::hasColumn('roles', 'business_id'), fn ($query) => $query->where('business_id', $this->businessId()))
            ->first();

        if ($role) {
            $user->syncRoles([$role]);
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

    protected function truthy($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'active', 'allowed', 'on'], true);
    }

    protected function businessId(): int
    {
        return (int) (session('user.business_id') ?? auth()->user()->business_id ?? 1);
    }

    protected function abortIfOutsideBusiness(User $user): void
    {
        if (Schema::hasColumn('users', 'business_id')) {
            abort_unless((int) $user->business_id === $this->businessId(), 404);
        }
    }
}
