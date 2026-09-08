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
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $username = env('ADMIN_USERNAME', 'admin');
        $password = env('ADMIN_PASSWORD', 'password');
        $name = env('ADMIN_NAME', 'Loan Admin');
        $firstName = env('ADMIN_FIRST_NAME', 'Loan');
        $lastName = env('ADMIN_LAST_NAME', 'Admin');
        $businessId = (int) env('ADMIN_BUSINESS_ID', 1);

        // 1. Ensure permissions exist if spatie permission is installed
        if (class_exists(Permission::class) && Schema::hasTable('permissions')) {
            $permissions = (array) config('loanmanagement.permissions', []);
            foreach ($permissions as $permissionName) {
                try {
                    Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
        }

        // 2. Ensure Admin Role exists and has all permissions
        $role = null;
        if (class_exists(Role::class) && Schema::hasTable('roles')) {
            try {
                $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
                if (class_exists(Permission::class) && Schema::hasTable('permissions')) {
                    $allPermissions = Permission::where('guard_name', 'web')->get();
                    if ($allPermissions->isNotEmpty() && method_exists($role, 'syncPermissions')) {
                        $role->syncPermissions($allPermissions);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 3. Create or update Admin User in main users table
        if (Schema::hasTable('users')) {
            $user = User::query()
                ->where('email', $email)
                ->orWhere('username', $username)
                ->first();

            if ($user) {
                $user->update([
                    'name' => $name,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'business_id' => $businessId,
                    'allow_login' => true,
                    'status' => 'active',
                ]);
            } else {
                $user = User::create([
                    'name' => $name,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'business_id' => $businessId,
                    'allow_login' => true,
                    'status' => 'active',
                ]);
            }

            if ($role && method_exists($user, 'assignRole')) {
                try {
                    $user->assignRole($role);
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
        }

        // 4. Also ensure in loan_users table if it exists (for dual connection / standalone setup)
        try {
            $loanConn = config('loanmanagement.db_connection', 'mysql_loan');
            if (Schema::connection($loanConn)->hasTable('loan_users')) {
                $exists = DB::connection($loanConn)->table('loan_users')
                    ->where('email', $email)
                    ->orWhere('username', $username)
                    ->first();

                $userData = [
                    'name' => $name,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'business_id' => $businessId,
                    'allow_login' => 1,
                    'status' => 'active',
                    'updated_at' => now(),
                ];

                if ($exists) {
                    DB::connection($loanConn)->table('loan_users')
                        ->where('id', $exists->id)
                        ->update($userData);
                } else {
                    $userData['created_at'] = now();
                    DB::connection($loanConn)->table('loan_users')->insert($userData);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if secondary table is not available
        }

        if (isset($this->command)) {
            $this->command->info('-----------------------------------------');
            $this->command->info('Admin user seeded successfully!');
            $this->command->info("Username : {$username}");
            $this->command->info("Email    : {$email}");
            $this->command->info("Password : {$password}");
            $this->command->info('-----------------------------------------');
        }
    }
}
