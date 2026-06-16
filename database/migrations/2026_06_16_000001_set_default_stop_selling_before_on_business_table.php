<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
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
        if (Schema::hasColumn('business', 'stop_selling_before')) {
            DB::statement("ALTER TABLE business MODIFY stop_selling_before INT NOT NULL DEFAULT 0 COMMENT 'Stop selling expired item n days before expiry'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('business', 'stop_selling_before')) {
            DB::statement("ALTER TABLE business MODIFY stop_selling_before INT NOT NULL COMMENT 'Stop selling expired item n days before expiry'");
        }
    }
};
