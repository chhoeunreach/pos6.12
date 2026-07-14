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
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'province_code')) {
                $table->string('province_code', 20)->nullable()->after('state')->index();
            }

            if (! Schema::hasColumn('contacts', 'district_code')) {
                $table->string('district_code', 20)->nullable()->after('province_code')->index();
            }

            if (! Schema::hasColumn('contacts', 'commune_code')) {
                $table->string('commune_code', 20)->nullable()->after('district_code')->index();
            }

            if (! Schema::hasColumn('contacts', 'village_code')) {
                $table->string('village_code', 20)->nullable()->after('commune_code')->index();
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
        Schema::table('contacts', function (Blueprint $table) {
            foreach (['village_code', 'commune_code', 'district_code', 'province_code'] as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
