<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = 'mysql_loan';

        if (Schema::connection($conn)->hasTable('loan_chat_threads')) {
            Schema::connection($conn)->table('loan_chat_threads', function (Blueprint $table) use ($conn) {
                $columns = Schema::connection($conn)->getColumnListing('loan_chat_threads');

                if (! in_array('last_message', $columns, true)) {
                    $table->longText('last_message')->nullable()->after('priority');
                }
                if (! in_array('last_message_type', $columns, true)) {
                    $table->string('last_message_type', 50)->nullable()->after('last_message');
                }
                if (! in_array('unread_customer_count', $columns, true)) {
                    $table->unsignedInteger('unread_customer_count')->default(0)->after('last_message_at');
                }
                if (! in_array('unread_staff_count', $columns, true)) {
                    $table->unsignedInteger('unread_staff_count')->default(0)->after('unread_customer_count');
                }

                if (! in_array('last_message_at', $columns, true)) {
                    $table->timestamp('last_message_at')->nullable()->after('last_message_type');
                }
            });
        }

        if (Schema::connection($conn)->hasTable('loan_chat_messages')) {
            Schema::connection($conn)->table('loan_chat_messages', function (Blueprint $table) use ($conn) {
                $columns = Schema::connection($conn)->getColumnListing('loan_chat_messages');

                if (! in_array('file_url', $columns, true)) {
                    $table->string('file_url', 2048)->nullable()->after('file_id');
                }
                if (! in_array('file_name', $columns, true)) {
                    $table->string('file_name')->nullable()->after('file_url');
                }
                if (! in_array('file_mime', $columns, true)) {
                    $table->string('file_mime', 191)->nullable()->after('file_name');
                }
                if (! in_array('file_size', $columns, true)) {
                    $table->unsignedBigInteger('file_size')->nullable()->after('file_mime');
                }
                if (! in_array('audio_duration_seconds', $columns, true)) {
                    $table->unsignedInteger('audio_duration_seconds')->nullable()->after('file_size');
                }
                if (! in_array('location_address', $columns, true)) {
                    $table->string('location_address')->nullable()->after('longitude');
                }
                if (! in_array('metadata', $columns, true)) {
                    $table->json('metadata')->nullable()->after('read_at');
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive down.
    }
};

