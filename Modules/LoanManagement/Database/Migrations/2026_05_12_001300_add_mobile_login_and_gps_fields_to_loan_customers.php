<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_customers')) {
            return;
        }

        Schema::connection('mysql_loan')->table('loan_customers', function (Blueprint $table) {
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'username')) {
                $table->string('username', 100)->nullable()->unique()->after('name');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'password')) {
                $table->string('password')->nullable()->after('username');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'login_phone')) {
                $table->string('login_phone', 50)->nullable()->unique()->after('phone');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'can_login')) {
                $table->boolean('can_login')->default(false)->after('login_phone');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('can_login');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'remember_token')) {
                $table->string('remember_token', 100)->nullable()->after('last_login_at');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'allow_gps_tracking')) {
                $table->boolean('allow_gps_tracking')->default(false)->after('remember_token');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'gps_tracking_started_at')) {
                $table->timestamp('gps_tracking_started_at')->nullable()->after('allow_gps_tracking');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'gps_tracking_stopped_at')) {
                $table->timestamp('gps_tracking_stopped_at')->nullable()->after('gps_tracking_started_at');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_customers', 'gps_tracking_note')) {
                $table->text('gps_tracking_note')->nullable()->after('gps_tracking_stopped_at');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive down.
    }
};

