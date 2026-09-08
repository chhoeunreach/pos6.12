<?php

namespace Database\Seeders;

use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            $this->command?->warn('users table does not exist. Run migrations first.');

            return;
        }

        $username = env('ADMIN_USERNAME') ?: $this->firstAdministratorUsername();
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');
        $firstName = env('ADMIN_FIRST_NAME', 'Loan');
        $lastName = env('ADMIN_LAST_NAME', 'Admin');

        $data = [
            'surname' => '',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'language' => env('APP_LOCALE', 'en'),
            'allow_login' => 1,
            'status' => 'active',
            'user_type' => 'user',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('users', 'business_id')) {
            $businessId = $this->validBusinessId((int) env('ADMIN_BUSINESS_ID', 1));
            if ($businessId !== null) {
                $data['business_id'] = $businessId;
            }
        }

        $data = $this->onlyExistingUserColumns($data);

        $user = User::withTrashed()
            ->where('username', $username)
            ->when($email, fn ($query) => $query->orWhere('email', $email))
            ->first();

        if ($user) {
            $user->restore();
            $user->forceFill($data)->save();
        } else {
            $data['created_at'] = now();
            $user = User::create($this->onlyExistingUserColumns($data));
        }

        $role = $this->ensureAdminRole();
        if ($role && method_exists($user, 'assignRole')) {
            $user->assignRole($role);
        }

        $this->command?->info('Admin user seeded successfully.');
        $this->command?->line("Username: {$username}");
        $this->command?->line("Email: {$email}");
        $this->command?->line("Password: {$password}");
    }

    private function firstAdministratorUsername(): string
    {
        $usernames = array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMINISTRATOR_USERNAMES', 'admin'))
        ));

        return $usernames[0] ?? 'admin';
    }

    private function onlyExistingUserColumns(array $data): array
    {
        return array_filter(
            $data,
            fn ($value, $column) => Schema::hasColumn('users', $column),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function validBusinessId(int $preferredBusinessId): ?int
    {
        if (! Schema::hasTable('business')) {
            return null;
        }

        $exists = DB::table('business')->where('id', $preferredBusinessId)->exists();
        if ($exists) {
            return $preferredBusinessId;
        }

        $firstBusinessId = DB::table('business')->orderBy('id')->value('id');

        return $firstBusinessId ? (int) $firstBusinessId : null;
    }

    private function ensureAdminRole(): ?Role
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            return null;
        }

        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        if (class_exists(Permission::class) && Schema::hasTable('permissions')) {
            $permissions = Permission::where('guard_name', 'web')->get();
            if ($permissions->isNotEmpty()) {
                $role->syncPermissions($permissions);
            }
        }

        return $role;
    }
}
