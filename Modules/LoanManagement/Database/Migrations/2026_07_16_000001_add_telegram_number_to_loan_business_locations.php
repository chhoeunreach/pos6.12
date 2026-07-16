<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_business_locations';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)
            || Schema::connection($this->connection)->hasColumn($this->table, 'telegram_number')) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $column = $table->string('telegram_number', 50)->nullable();
            if (Schema::connection($this->connection)->hasColumn($this->table, 'phone')) {
                $column->after('phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)
            || ! Schema::connection($this->connection)->hasColumn($this->table, 'telegram_number')) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->dropColumn('telegram_number');
        });
    }
};
