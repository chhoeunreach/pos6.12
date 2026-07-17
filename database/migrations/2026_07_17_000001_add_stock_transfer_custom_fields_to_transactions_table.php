<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transfer_custom_field_1')->nullable()->after('additional_notes');
            $table->string('transfer_custom_field_2')->nullable()->after('transfer_custom_field_1');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['transfer_custom_field_1', 'transfer_custom_field_2']);
        });
    }
};
