<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('export_histories')) {
            return;
        }

        Schema::create('export_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->string('type', 60)->index();
            $table->json('filters')->nullable();
            $table->string('format', 10)->default('csv');
            $table->string('filename')->nullable();
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->string('path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('download_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'type', 'status']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_histories');
    }
};
