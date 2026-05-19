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
            || Schema::connection($this->connection)->hasColumn($this->table, 'loan_invoice_prefix')) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->string('loan_invoice_prefix', 50)->nullable()->after('location_code');
        });
    }

    public function down(): void
    {
        // Non-destructive for production compatibility.
    }
};
