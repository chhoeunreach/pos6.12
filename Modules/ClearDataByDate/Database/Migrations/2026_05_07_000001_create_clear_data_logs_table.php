<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clear_data_logs')) {
            return;
        }

        Schema::create('clear_data_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->date('date_from')->index();
            $table->date('date_to')->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->json('selected_modules')->nullable();
            $table->json('preview_counts')->nullable();
            $table->json('total_deleted')->nullable();
            $table->string('status', 50)->default('previewed')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clear_data_logs');
    }
};

