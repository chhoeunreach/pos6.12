<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_accounts')) {
            Schema::create('financial_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('business_location_id')->nullable()->index();
                $table->string('account_code', 50)->nullable();
                $table->string('account_name', 191);
                $table->enum('account_type', ['cash', 'bank', 'digital_wallet', 'petty_cash'])->default('cash')->index();
                $table->string('account_number', 191)->nullable();
                $table->string('bank_name', 191)->nullable();
                $table->string('currency', 10)->default('USD');
                $table->decimal('opening_balance', 22, 4)->default(0.0000);
                $table->decimal('current_balance', 22, 4)->default(0.0000);
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'business_location_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};

