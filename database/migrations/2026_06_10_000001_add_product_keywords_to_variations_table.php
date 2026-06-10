<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('variations', function (Blueprint $table) {
            if (! Schema::hasColumn('variations', 'product_keywords')) {
                $table->string('product_keywords')->nullable()->after('sub_sku');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('variations', function (Blueprint $table) {
            if (Schema::hasColumn('variations', 'product_keywords')) {
                $table->dropColumn('product_keywords');
            }
        });
    }
};
