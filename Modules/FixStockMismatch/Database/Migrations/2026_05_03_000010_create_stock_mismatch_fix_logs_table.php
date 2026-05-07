<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_mismatch_fix_logs')) {
            return;
        }

        Schema::create('stock_mismatch_fix_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variation_id');
            $table->decimal('old_qty', 22, 4)->default(0);
            $table->decimal('new_qty', 22, 4)->default(0);
            $table->decimal('difference', 22, 4)->default(0);
            $table->unsignedInteger('fixed_by');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'location_id']);
            $table->index(['variation_id', 'product_id']);
            $table->index('fixed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mismatch_fix_logs');
    }
};

