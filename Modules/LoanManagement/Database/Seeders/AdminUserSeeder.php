<?php

namespace Modules\LoanManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

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

        // Admin setup is isolated to the LoanManagement database. The main POS
        // users table must remain owned by the main application.
        try {
            $loanConn = config('loanmanagement.db_connection', 'mysql_loan');
            if (Schema::connection($loanConn)->hasTable('loan_users')) {
                $exists = DB::connection($loanConn)->table('loan_users')
                    ->where('email', $email)
                    ->orWhere('username', $username)
                    ->first();

                $userData = $this->loanUserColumns([
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
                ]);

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

    private function loanUserColumns(array $payload): array
    {
        $columns = Schema::connection(config('loanmanagement.db_connection', 'mysql_loan'))
            ->getColumnListing('loan_users');

        return array_intersect_key($payload, array_flip($columns));
    }
}
