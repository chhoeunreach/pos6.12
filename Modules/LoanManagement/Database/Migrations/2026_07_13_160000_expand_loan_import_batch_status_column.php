<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_batches')
            || ! Schema::connection($this->connection)->hasColumn('loan_import_batches', 'status')) {
            return;
        }

        DB::connection($this->connection)->statement(
            "ALTER TABLE `loan_import_batches` MODIFY `status` varchar(30) NOT NULL DEFAULT 'uploaded'"
        );
    }

    public function down(): void
    {
        // Do not shrink the column; existing statuses may exceed 20 characters.
    }
};
