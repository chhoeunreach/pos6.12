<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('smart_stock_price_compares')) {
            Schema::create('smart_stock_price_compares', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->string('ref_no', 60)->index();
                $table->string('title', 191)->nullable();
                $table->date('compare_date')->index();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'ref_no'], 'sspc_cmp_biz_ref_uq');
                $table->index(['business_id', 'compare_date'], 'sspc_cmp_biz_date_idx');
            });
        }

        if (! Schema::hasTable('smart_stock_price_compare_lines')) {
            Schema::create('smart_stock_price_compare_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('price_compare_id')->index();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('product_id')->index();
                $table->unsignedInteger('variation_id')->index();
                $table->string('sku', 191)->index();
                $table->decimal('qty', 22, 4)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['price_compare_id', 'sort_order'], 'sspc_lines_cmp_sort_idx');
                $table->index(['business_id', 'variation_id'], 'sspc_lines_biz_var_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_stock_price_compare_lines');
        Schema::dropIfExists('smart_stock_price_compares');
    }
};
