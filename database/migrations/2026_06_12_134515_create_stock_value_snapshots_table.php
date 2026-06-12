<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->integer('business_id')->unsigned();
            $table->integer('location_id')->unsigned()->nullable();
            $table->decimal('stock_value_by_purchase_price', 22, 2)->default(0);
            $table->decimal('stock_value_by_sale_price', 22, 2)->default(0);
            $table->date('snapshot_date');
            $table->timestamp('calculated_at')->useCurrent();
            $table->unique(['business_id', 'location_id', 'snapshot_date'], 'upos_stock_snap_biz_loc_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_value_snapshots');
    }
};
