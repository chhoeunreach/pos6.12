<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('accessories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('model')->nullable();
            $table->decimal('price', 22, 4)->default(0);
            $table->decimal('cost', 22, 4)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('accessories');
    }
};
