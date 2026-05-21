<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('import_histories')) {
            return;
        }

        Schema::create('import_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->string('type', 40)->index();
            $table->string('filename');
            $table->string('stored_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('queue_batch_id')->nullable()->index();
            $table->string('duplicate_mode', 20)->default('skip');
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'type', 'status']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_histories');
    }
};
