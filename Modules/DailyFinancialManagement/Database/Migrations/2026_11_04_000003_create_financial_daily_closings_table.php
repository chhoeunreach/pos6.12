<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_daily_closings')) {
            Schema::create('financial_daily_closings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('business_location_id')->index();
                $table->date('closing_date')->index();
                $table->enum('closing_status', ['draft', 'submitted', 'approved', 'locked'])->default('draft')->index();

                // P&L summary for the day
                $table->decimal('total_pos_sales', 22, 4)->default(0.0000);
                $table->decimal('total_pos_returns', 22, 4)->default(0.0000);
                $table->decimal('total_net_sales', 22, 4)->default(0.0000);
                $table->decimal('total_cogs', 22, 4)->default(0.0000);
                $table->decimal('total_gross_profit', 22, 4)->default(0.0000);
                $table->decimal('total_manual_income', 22, 4)->default(0.0000);
                $table->decimal('total_loan_interest', 22, 4)->default(0.0000);
                $table->decimal('total_operating_expenses', 22, 4)->default(0.0000);
                $table->decimal('net_profit', 22, 4)->default(0.0000);

                // Cash Flow and Reconciliation Summary
                $table->decimal('opening_cash_balance', 22, 4)->default(0.0000);
                $table->decimal('total_cash_inflow', 22, 4)->default(0.0000);
                $table->decimal('total_cash_outflow', 22, 4)->default(0.0000);
                $table->decimal('expected_cash_balance', 22, 4)->default(0.0000);
                $table->decimal('actual_cash_counted', 22, 4)->default(0.0000);
                $table->decimal('cash_difference', 22, 4)->default(0.0000);

                // Bank Summary
                $table->decimal('expected_bank_balance', 22, 4)->default(0.0000);
                $table->decimal('actual_bank_balance', 22, 4)->default(0.0000);
                $table->decimal('bank_difference', 22, 4)->default(0.0000);

                $table->text('closing_notes')->nullable();
                $table->text('reconciliation_notes')->nullable();
                $table->unsignedInteger('closed_by')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('locked_at')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'business_location_id', 'closing_date'], 'unique_branch_daily_closing');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_daily_closings');
    }
};

