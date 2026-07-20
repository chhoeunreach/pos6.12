<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fully separate storage for the Telegram customer-chat bridge. This is deliberately NOT
 * loan_chat_threads/loan_chat_messages - those tables back the staff's own internal
 * "Live Chat" tool and must never be touched by the Telegram integration.
 */
return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_telegram_chat_threads')) {
            Schema::connection($this->connection)->create('loan_telegram_chat_threads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('status', 20)->default('open')->index();
                $table->longText('last_message')->nullable();
                $table->string('last_message_type', 40)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('unread_staff_count')->default(0);
                $table->unsignedInteger('unread_customer_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('loan_telegram_chat_messages')) {
            Schema::connection($this->connection)->create('loan_telegram_chat_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('thread_id');
                $table->string('sender_type', 20);
                $table->unsignedBigInteger('sender_id');
                $table->string('sender_name_snapshot')->nullable();
                $table->longText('message')->nullable();
                $table->string('message_type', 20)->default('text');
                $table->unsignedBigInteger('file_id')->nullable();
                $table->string('file_url', 2048)->nullable();
                $table->string('file_name')->nullable();
                $table->string('file_mime', 191)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedInteger('audio_duration_seconds')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('location_address')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['thread_id', 'created_at'], 'lm_tg_chat_messages_thread_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('loan_telegram_chat_messages');
        Schema::connection($this->connection)->dropIfExists('loan_telegram_chat_threads');
    }
};
