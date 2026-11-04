<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_pos_imports')) {
            Schema::create('financial_pos_imports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->string('source_module', 50)->default('pos')->index();
                $table->string('sync_type', 30)->default('incremental');
                $table->dateTime('date_from')->nullable();
                $table->dateTime('date_to')->nullable();
                $table->unsignedInteger('records_processed')->default(0);
                $table->unsignedInteger('records_imported')->default(0);
                $table->unsignedInteger('records_skipped')->default(0);
                $table->unsignedInteger('records_failed')->default(0);
                $table->enum('status', ['running', 'completed', 'failed'])->default('running')->index();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('executed_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_pos_imports');
    }
};

