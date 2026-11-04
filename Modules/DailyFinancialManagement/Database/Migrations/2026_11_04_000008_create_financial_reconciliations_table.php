<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_reconciliations')) {
            Schema::create('financial_reconciliations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('daily_closing_id')->nullable()->index();
                $table->unsignedBigInteger('financial_account_id')->index();
                $table->date('reconciliation_date')->index();
                $table->decimal('system_balance', 22, 4);
                $table->decimal('physical_balance', 22, 4);
                $table->decimal('difference', 22, 4);
                $table->json('denominations_json')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('reconciled_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliations');
    }
};

