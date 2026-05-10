<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smart_stock_inventory_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('location_id')->index();
            $table->string('name');
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->unsignedInteger('completed_by')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('smart_stock_inventory_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->unsignedInteger('variation_id')->nullable()->index();
            $table->string('sku', 191)->nullable()->index();
            $table->string('product_name')->nullable();
            $table->string('variation_name')->nullable();
            $table->string('imei', 191)->nullable()->index();
            $table->string('lot_number', 191)->nullable()->index();
            $table->decimal('system_qty', 22, 4)->default(0);
            $table->decimal('actual_qty', 22, 4)->default(0);
            $table->decimal('difference_qty', 22, 4)->default(0);
            $table->string('status', 40)->default('matched')->index();
            $table->string('remark', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('smart_stock_inventory_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->timestamp('movement_date')->index();
            $table->string('reference_no', 191)->nullable()->index();
            $table->string('transaction_type', 100)->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->string('location_name', 255)->nullable();
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->unsignedInteger('variation_id')->nullable()->index();
            $table->string('product_name', 255)->nullable();
            $table->string('sku', 191)->nullable()->index();
            $table->string('imei', 191)->nullable()->index();
            $table->string('lot_number', 191)->nullable()->index();
            $table->decimal('qty_in', 22, 4)->default(0);
            $table->decimal('qty_out', 22, 4)->default(0);
            $table->decimal('balance_qty', 22, 4)->default(0);
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->string('created_by_name', 255)->nullable();
        });

        Schema::create('smart_stock_mismatch_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->unsignedInteger('variation_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->string('problem', 191)->index();
            $table->string('severity', 40)->index();
            $table->longText('payload')->nullable();
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('smart_stock_fix_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->string('fix_type', 100)->index();
            $table->string('reference_type', 100)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->longText('before_payload')->nullable();
            $table->longText('after_payload')->nullable();
            $table->unsignedTinyInteger('is_rollback')->default(0)->index();
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('smart_imei_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->string('imei', 191)->index();
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->unsignedInteger('variation_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->string('status', 50)->default('in_stock')->index();
            $table->string('reference_type', 100)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->decimal('qty', 22, 4)->default(1);
            $table->timestamp('movement_date')->index();
            $table->unsignedInteger('created_by')->nullable()->index();
        });

        Schema::create('smart_lot_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->string('lot_number', 191)->index();
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->unsignedInteger('variation_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->decimal('qty_in', 22, 4)->default(0);
            $table->decimal('qty_out', 22, 4)->default(0);
            $table->decimal('balance_qty', 22, 4)->default(0);
            $table->string('reference_type', 100)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->timestamp('movement_date')->index();
            $table->unsignedInteger('created_by')->nullable()->index();
        });

        Schema::create('smart_stock_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->unique();
            $table->unsignedTinyInteger('telegram_enabled')->default(0);
            $table->string('telegram_bot_token', 255)->nullable();
            $table->string('telegram_chat_id', 255)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_stock_settings');
        Schema::dropIfExists('smart_lot_histories');
        Schema::dropIfExists('smart_imei_histories');
        Schema::dropIfExists('smart_stock_fix_logs');
        Schema::dropIfExists('smart_stock_mismatch_logs');
        Schema::dropIfExists('smart_stock_inventory_logs');
        Schema::dropIfExists('smart_stock_inventory_lines');
        Schema::dropIfExists('smart_stock_inventory_sessions');
    }
};
