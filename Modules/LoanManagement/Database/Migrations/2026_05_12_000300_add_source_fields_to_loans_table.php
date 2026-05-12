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
                $table->string('source_type')->nullable()->after('status');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'source_transaction_id')) {
                $table->unsignedBigInteger('source_transaction_id')->nullable()->after('source_type');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'source_invoice_no')) {
                $table->string('source_invoice_no')->nullable()->after('source_transaction_id');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'sell_final_total_snapshot')) {
                $table->decimal('sell_final_total_snapshot', 22, 4)->nullable()->after('source_invoice_no');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'sell_paid_amount_snapshot')) {
                $table->decimal('sell_paid_amount_snapshot', 22, 4)->nullable()->after('sell_final_total_snapshot');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'sell_due_amount_snapshot')) {
                $table->decimal('sell_due_amount_snapshot', 22, 4)->nullable()->after('sell_paid_amount_snapshot');
            }
        });
    }

    public function down(): void
    {
        // keep snapshot columns for data integrity
    }
};
