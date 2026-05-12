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
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'source_created_at')) {
                $table->timestamp('source_created_at')->nullable()->after('source_invoice_no');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'stock_already_deducted')) {
                $table->boolean('stock_already_deducted')->default(true)->after('source_created_at');
            }
        });
    }

    public function down(): void
    {
        // Keep columns for audit consistency.
    }
};
