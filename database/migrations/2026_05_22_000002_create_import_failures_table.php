<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('import_failures')) {
            return;
        }

        Schema::create('import_failures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_history_id')->index();
            $table->unsignedInteger('row_number')->index();
            $table->json('raw_data')->nullable();
            $table->text('error_message');
            $table->timestamp('created_at')->nullable();

            $table->foreign('import_history_id')
                ->references('id')
                ->on('import_histories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_failures');
    }
};
