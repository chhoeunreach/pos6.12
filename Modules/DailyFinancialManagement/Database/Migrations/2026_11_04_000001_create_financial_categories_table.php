<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_categories')) {
            Schema::create('financial_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->string('code', 50)->nullable();
                $table->string('name_en', 191);
                $table->string('name_kh', 191)->nullable();
                $table->enum('type', [
                    'income',
                    'operating_expense',
                    'cost_of_goods_sold',
                    'non_operating_income',
                    'non_operating_expense',
                    'asset',
                    'liability',
                    'equity',
                ])->default('operating_expense')->index();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->boolean('is_operating')->default(true);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->text('description')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_categories');
    }
};

