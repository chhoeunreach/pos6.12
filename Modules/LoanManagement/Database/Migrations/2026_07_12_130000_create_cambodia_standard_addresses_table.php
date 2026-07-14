<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cambodia_standard_addresses')) {
            return;
        }

        Schema::create('cambodia_standard_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('province_code', 20)->index();
            $table->string('province_kh')->nullable();
            $table->string('province_en')->nullable();
            $table->string('district_code', 20)->index();
            $table->string('district_kh')->nullable();
            $table->string('district_en')->nullable();
            $table->string('commune_code', 20)->index();
            $table->string('commune_kh')->nullable();
            $table->string('commune_en')->nullable();
            $table->string('village_code', 20)->unique();
            $table->string('village_kh')->nullable();
            $table->string('village_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambodia_standard_addresses');
    }
};
