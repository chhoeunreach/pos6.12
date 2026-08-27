<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_payment_schedules';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)
            || Schema::connection($this->connection)->hasColumn($this->table, 'discount_amount')) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->decimal('discount_amount', 18, 2)->default(0)->after('paid_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)
            || ! Schema::connection($this->connection)->hasColumn($this->table, 'discount_amount')) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
