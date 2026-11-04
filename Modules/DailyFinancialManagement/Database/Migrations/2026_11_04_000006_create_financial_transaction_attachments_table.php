<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_transaction_attachments')) {
            Schema::create('financial_transaction_attachments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('financial_transaction_id')->index();
                $table->string('file_path', 255);
                $table->string('file_name', 255);
                $table->string('file_type', 50)->nullable();
                $table->unsignedInteger('file_size')->default(0);
                $table->unsignedInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transaction_attachments');
    }
};

