<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_transactions')) {
            Schema::create('financial_transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('business_location_id')->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->unsignedBigInteger('financial_account_id')->nullable()->index();

                $table->enum('transaction_type', [
                    'income',
                    'expense',
                    'pos_sale',
                    'pos_return',
                    'loan_interest',
                    'loan_principal',
                    'transfer',
                    'adjustment',
                ])->index();

                $table->date('transaction_date')->index();
                $table->string('ref_no', 191)->index();
                $table->string('source_type', 50)->default('manual')->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();

                $table->text('description');
                $table->decimal('amount', 22, 4);
                $table->decimal('cogs_amount', 22, 4)->default(0.0000);
                $table->string('currency', 10)->default('USD');
                $table->decimal('exchange_rate', 20, 4)->default(1.0000);
                $table->decimal('amount_in_base_currency', 22, 4);

                $table->string('payment_method', 50)->default('cash')->index();
                $table->enum('approval_status', [
                    'draft',
                    'pending',
                    'reviewed',
                    'approved',
                    'rejected',
                    'cancelled',
                    'reversed',
                ])->default('draft')->index();

                $table->boolean('is_operating')->default(true);
                $table->boolean('is_locked')->default(false)->index();
                $table->unsignedBigInteger('daily_closing_id')->nullable()->index();

                $table->unsignedInteger('created_by')->nullable()->index();
                $table->unsignedInteger('reviewed_by')->nullable()->index();
                $table->dateTime('reviewed_at')->nullable();
                $table->unsignedInteger('approved_by')->nullable()->index();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
                $table->json('meta_json')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['business_id', 'transaction_date']);
                $table->index(['business_id', 'business_location_id', 'transaction_date']);
                $table->unique(['business_id', 'source_type', 'source_id'], 'unique_business_source_txn');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};

