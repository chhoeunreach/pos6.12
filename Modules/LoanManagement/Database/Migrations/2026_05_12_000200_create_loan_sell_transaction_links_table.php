<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_sell_transaction_links')) {
            Schema::connection('mysql_loan')->create('loan_sell_transaction_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('transaction_id');
                $table->unsignedBigInteger('loan_id');
                $table->string('invoice_no_snapshot')->nullable();
                $table->string('customer_name_snapshot')->nullable();
                $table->string('location_name_snapshot')->nullable();
                $table->decimal('final_total_snapshot', 22, 4)->default(0);
                $table->decimal('paid_amount_snapshot', 22, 4)->default(0);
                $table->decimal('due_amount_snapshot', 22, 4)->default(0);
                $table->unsignedBigInteger('converted_by')->nullable();
                $table->string('converted_by_name_snapshot')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->unique('transaction_id');
                $table->index('loan_id');
            });
        }
    }

    public function down(): void
    {
        // keep snapshots for audit
    }
};
