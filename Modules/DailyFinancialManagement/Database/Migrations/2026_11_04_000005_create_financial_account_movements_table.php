<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_account_movements')) {
            Schema::create('financial_account_movements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('financial_account_id')->index();
                $table->unsignedBigInteger('financial_transaction_id')->nullable()->index();
                $table->enum('movement_type', ['inflow', 'outflow', 'transfer_in', 'transfer_out'])->index();
                $table->date('movement_date')->index();
                $table->decimal('amount', 22, 4);
                $table->decimal('balance_before', 22, 4)->default(0.0000);
                $table->decimal('balance_after', 22, 4)->default(0.0000);
                $table->unsignedBigInteger('related_account_id')->nullable()->index();
                $table->string('ref_no', 191)->nullable()->index();
                $table->string('source_type', 50)->default('manual')->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'movement_date'], 'idx_fin_mv_biz_date');
                $table->index(['financial_account_id', 'movement_date'], 'idx_fin_mv_acc_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_account_movements');
    }
};

