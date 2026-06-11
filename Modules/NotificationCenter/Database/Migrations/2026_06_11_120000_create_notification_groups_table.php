<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('notification_groups')) {
            return;
        }
        Schema::create('notification_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('chat_id');
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->string('module_type', 100)->nullable()->index();
            $table->boolean('send_text')->default(true);
            $table->boolean('send_pdf')->default(true);
            $table->boolean('active')->default(true);
            $table->string('direction', 10)->nullable()->comment('from or to');
            $table->string('location_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_groups');
    }
};
