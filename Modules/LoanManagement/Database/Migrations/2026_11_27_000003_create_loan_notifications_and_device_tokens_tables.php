<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_loan');

        if (!$schema->hasTable('loan_device_tokens')) {
            $schema->create('loan_device_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_type', 32)->index(); // staff | customer
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->string('device_id', 128)->nullable();
                $table->string('platform', 32)->default('android'); // android | ios | web
                $table->string('fcm_token', 512);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['user_type', 'user_id', 'fcm_token'], 'uniq_loan_device_token');
            });
        }

        if (!$schema->hasTable('loan_notifications')) {
            $schema->create('loan_notifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->string('recipient_type', 32)->index(); // staff | customer
                $table->unsignedBigInteger('recipient_id')->index();
                $table->string('category', 64)->index(); // chat | payment | reminder | location_request | system | feed
                $table->string('title', 255);
                $table->text('body');
                $table->json('data')->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('pushed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_loan')->dropIfExists('loan_notifications');
        Schema::connection('mysql_loan')->dropIfExists('loan_device_tokens');
    }
};
