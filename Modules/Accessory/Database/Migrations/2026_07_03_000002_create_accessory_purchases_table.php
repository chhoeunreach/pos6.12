<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('accessory_purchases', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('reference_no');
            $table->dateTime('transaction_date');
            $table->string('status')->default('ordered');
            $table->string('payment_status')->default('due');
            $table->decimal('total_cost', 22, 4)->default(0);
            $table->text('additional_notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::connection('mysql')->create('accessory_purchase_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('accessory_purchase_id');
            $table->unsignedInteger('accessory_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 22, 4)->default(1);
            $table->decimal('unit_cost', 22, 4)->default(0);
            $table->decimal('subtotal', 22, 4)->default(0);
            $table->timestamps();

            $table->foreign('accessory_purchase_id', 'api_purchase_id_fk')
                  ->references('id')->on('accessory_purchases')->onDelete('cascade');
            $table->foreign('accessory_id', 'api_item_accessory_fk')
                  ->references('id')->on('accessories')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('accessory_purchase_items');
        Schema::connection('mysql')->dropIfExists('accessory_purchases');
    }
};
