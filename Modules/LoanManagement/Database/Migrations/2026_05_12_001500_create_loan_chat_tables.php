<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_chat_threads')) {
            Schema::connection('mysql_loan')->create('loan_chat_threads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('thread_number', 64)->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('staff_id')->nullable();
                $table->unsignedBigInteger('loan_id')->nullable();
                $table->string('subject')->nullable();
                $table->enum('type', ['customer_staff', 'customer_collector', 'customer_admin', 'staff_admin'])->default('customer_staff');
                $table->enum('status', ['open', 'pending', 'closed'])->default('open');
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
                $table->longText('last_message')->nullable();
                $table->string('last_message_type', 50)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('unread_customer_count')->default(0);
                $table->unsignedInteger('unread_staff_count')->default(0);
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->enum('created_by_type', ['customer', 'staff', 'admin']);
                $table->unsignedBigInteger('created_by_id');
                $table->timestamps();
                $table->softDeletes();
                $table->index(['customer_id', 'staff_id', 'status'], 'loan_chat_threads_customer_staff_status_idx');
            });
        }

        if (! Schema::connection('mysql_loan')->hasTable('loan_chat_messages')) {
            Schema::connection('mysql_loan')->create('loan_chat_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('thread_id');
                $table->enum('sender_type', ['customer', 'staff', 'admin']);
                $table->unsignedBigInteger('sender_id');
                $table->string('sender_name_snapshot')->nullable();
                $table->longText('message')->nullable();
                $table->enum('message_type', ['text', 'image', 'file', 'audio', 'location', 'system'])->default('text');
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
                $table->string('local_uuid', 80)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['thread_id', 'created_at'], 'loan_chat_messages_thread_created_idx');
                $table->unique(['thread_id', 'sender_type', 'sender_id', 'local_uuid'], 'loan_chat_messages_local_uuid_unique');
            });
        }

        if (! Schema::connection('mysql_loan')->hasTable('loan_chat_participants')) {
            Schema::connection('mysql_loan')->create('loan_chat_participants', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('thread_id');
                $table->enum('participant_type', ['customer', 'staff', 'admin']);
                $table->unsignedBigInteger('participant_id');
                $table->string('participant_name_snapshot')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();
                $table->unique(['thread_id', 'participant_type', 'participant_id'], 'loan_chat_participants_unique');
            });
        }
    }

    public function down(): void
    {
        // Non-destructive down.
    }
};
