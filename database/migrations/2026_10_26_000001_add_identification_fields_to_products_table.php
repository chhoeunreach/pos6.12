<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('model_number')->nullable();
            $table->string('qa_number')->nullable();
            $table->string('country_of_manufacture')->nullable();
            $table->string('network_connectivity')->nullable();
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['model_number', 'qa_number', 'country_of_manufacture', 'network_connectivity']);
        });
    }
};
