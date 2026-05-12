<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')) {
            return;
        }

        Schema::connection('mysql_loan')->table('loans', function (Blueprint $table) {
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'source_type')) {
                $table->string('source_type')->nullable()->default('ultimate_pos_sell')->after('status');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'source_transaction_id')) {
                $table->unsignedBigInteger('source_transaction_id')->nullable()->after('source_type');
                $table->index('source_transaction_id');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'source_invoice_no')) {
                $table->string('source_invoice_no')->nullable()->after('source_transaction_id');
            }
        });
    }

    public function down(): void
    {
        // Keep source columns for historical integrity.
    }
};
