<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('notification_logs')) {
            return;
        }
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module_type', 100)->index();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->text('message')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status', 50)->default('pending');
            $table->longText('response')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['module_type', 'reference_type', 'reference_id'], 'nc_logs_lookup');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_logs');
    }
};
