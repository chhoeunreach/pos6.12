<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class LoanUserSyncService
{
    public function syncFromAuthenticatedUser($user): ?int
    {
        if (! $user) {
            return null;
        }

        $connection = (string) config('loanmanagement.db_connection', 'mysql_loan');

        try {
            $table = $this->ensureModuleUserTable($connection);
            if (! $table) {
                return null;
            }

            $schema = Schema::connection($connection);
            $columns = $schema->getColumnListing($table);
            $now = now();

            $username = trim((string) ($user->username ?? ''));
            $email = trim((string) ($user->email ?? ''));

            if ($username === '' && $email === '') {
                $username = 'pos_user_'.$user->id;
            }

            $payload = array_filter([
                'name' => $this->nameFor($user),
                'surname' => (string) ($user->surname ?? ''),
                'first_name' => (string) ($user->first_name ?? $this->nameFor($user)),
                'last_name' => (string) ($user->last_name ?? ''),
                'username' => $username !== '' ? $username : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $this->phoneFor($user),
                'language' => (string) ($user->language ?? config('app.locale', 'en')),
                'password' => (string) ($user->password ?? ''),
                'business_id' => (int) ($user->business_id ?? 1),
                'allow_login' => 1,
                'status' => $this->activeStatusFor($user),
                'user_type' => (string) ($user->user_type ?? 'user'),
                'deleted_at' => null,
                'updated_at' => $now,
            ], function ($value, $column) use ($columns) {
                return in_array($column, $columns, true) && $value !== null;
            }, ARRAY_FILTER_USE_BOTH);

            $existing = DB::connection($connection)->table($table)
                ->when($username !== '', fn ($query) => $query->orWhere('username', $username))
                ->when($email !== '', fn ($query) => $query->orWhere('email', $email))
                ->first();

            if ($existing) {
                DB::connection($connection)->table($table)
                    ->where('id', $existing->id)
                    ->update($payload);

                return (int) $existing->id;
            }

            if (in_array('created_at', $columns, true)) {
                $payload['created_at'] = $now;
            }

            return (int) DB::connection($connection)->table($table)->insertGetId($payload);
        } catch (\Throwable $e) {
            Log::warning('LoanManagement loan user auto-sync failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function ensureModuleUserTable(string $connection): ?string
    {
        $schema = Schema::connection($connection);

        if ($schema->hasTable('loan_users')) {
            return 'loan_users';
        }

        if ($schema->hasTable('users')) {
            return 'users';
        }

        $schema->create('loan_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        return 'loan_users';
    }

    private function nameFor($user): string
    {
        $name = trim((string) ($user->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($user->first_name ?? '').' '.(string) ($user->last_name ?? '')) ?: 'Loan User';
    }

    private function phoneFor($user): ?string
    {
        foreach (['contact_no', 'contact_number', 'mobile', 'phone'] as $column) {
            $value = trim((string) ($user->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function activeStatusFor($user): string
    {
        $status = (string) ($user->status ?? 'active');

        return in_array($status, ['active', '1', ''], true) ? 'active' : $status;
    }
}
