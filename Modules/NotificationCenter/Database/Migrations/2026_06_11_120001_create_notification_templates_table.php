<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('notification_center_templates')) {
            return;
        }
        Schema::create('notification_center_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module_type', 100)->index();
            $table->string('title');
            $table->text('message_template');
            $table->string('pdf_template_view')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_center_templates');
    }
};
