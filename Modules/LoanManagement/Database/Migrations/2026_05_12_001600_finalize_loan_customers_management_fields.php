<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'mysql_loan';
    protected string $table = 'loan_customers';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $this->addIfMissing($table, 'blacklist_date', fn () => $table->timestamp('blacklist_date')->nullable()->after('blacklist_reason'));
            $this->addIfMissing($table, 'blacklist_by', fn () => $table->unsignedBigInteger('blacklist_by')->nullable()->after('blacklist_date'));
            $this->addIfMissing($table, 'remember_token', fn () => $table->string('remember_token', 100)->nullable()->after('last_login_at'));
            $this->addIfMissing($table, 'customer_code', fn () => $table->string('customer_code', 50)->nullable()->after('main_contact_id'));
            $this->addIfMissing($table, 'login_phone', fn () => $table->string('login_phone', 50)->nullable()->after('phone'));
            $this->addIfMissing($table, 'username', fn () => $table->string('username', 100)->nullable()->after('name'));
            $this->addIfMissing($table, 'password', fn () => $table->string('password')->nullable()->after('username'));
            $this->addIfMissing($table, 'can_login', fn () => $table->boolean('can_login')->default(false)->after('password'));
            $this->addIfMissing($table, 'last_login_at', fn () => $table->timestamp('last_login_at')->nullable()->after('can_login'));
            $this->addIfMissing($table, 'allow_gps_tracking', fn () => $table->boolean('allow_gps_tracking')->default(false)->after('remember_token'));
            $this->addIfMissing($table, 'gps_tracking_started_at', fn () => $table->timestamp('gps_tracking_started_at')->nullable()->after('allow_gps_tracking'));
            $this->addIfMissing($table, 'gps_tracking_stopped_at', fn () => $table->timestamp('gps_tracking_stopped_at')->nullable()->after('gps_tracking_started_at'));
            $this->addIfMissing($table, 'gps_tracking_note', fn () => $table->text('gps_tracking_note')->nullable()->after('gps_tracking_stopped_at'));
        });

        try {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->unique('customer_code', 'loan_customers_customer_code_unique_2');
            });
        } catch (\Throwable $e) {
        }
        try {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->unique('login_phone', 'loan_customers_login_phone_unique');
            });
        } catch (\Throwable $e) {
        }
        try {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->unique('username', 'loan_customers_username_unique');
            });
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // keep non-destructive
    }

    protected function addIfMissing(Blueprint $table, string $column, callable $create): void
    {
        if (! Schema::connection($this->connection)->hasColumn($this->table, $column)) {
            $create();
        }
    }
};

