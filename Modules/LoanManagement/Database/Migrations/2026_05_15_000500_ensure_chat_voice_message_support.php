<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = 'mysql_loan';

        if (Schema::connection($connection)->hasTable('loan_chat_messages')) {
            Schema::connection($connection)->table('loan_chat_messages', function (Blueprint $table) use ($connection) {
                $columns = Schema::connection($connection)->getColumnListing('loan_chat_messages');

                if (! in_array('message_type', $columns, true)) {
                    $table->string('message_type', 50)->default('text')->after('message');
                }
                if (! in_array('file_id', $columns, true)) {
                    $table->unsignedBigInteger('file_id')->nullable()->after('message_type');
                }
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
                if (! in_array('metadata', $columns, true)) {
                    $table->json('metadata')->nullable()->after('read_at');
                }
                if (! in_array('local_uuid', $columns, true)) {
                    $table->string('local_uuid', 80)->nullable()->after('metadata');
                }
                if (! in_array('delivered_at', $columns, true)) {
                    $table->timestamp('delivered_at')->nullable()->after('local_uuid');
                }
                if (! in_array('read_by_customer_at', $columns, true)) {
                    $table->timestamp('read_by_customer_at')->nullable()->after('delivered_at');
                }
                if (! in_array('read_by_staff_at', $columns, true)) {
                    $table->timestamp('read_by_staff_at')->nullable()->after('read_by_customer_at');
                }
            });
        }

        if (Schema::connection($connection)->hasTable('loan_files')) {
            Schema::connection($connection)->table('loan_files', function (Blueprint $table) use ($connection) {
                $columns = Schema::connection($connection)->getColumnListing('loan_files');

                if (! in_array('file_type', $columns, true)) {
                    $table->string('file_type', 50)->nullable();
                }
                if (! in_array('disk', $columns, true)) {
                    $table->string('disk', 50)->default('public');
                }
                if (! in_array('storage_provider', $columns, true)) {
                    $table->string('storage_provider', 50)->default('local');
                }
                if (! in_array('path', $columns, true)) {
                    $table->string('path')->nullable();
                }
                if (! in_array('url', $columns, true)) {
                    $table->string('url', 2048)->nullable();
                }
                if (! in_array('original_name', $columns, true)) {
                    $table->string('original_name')->nullable();
                }
                if (! in_array('mime_type', $columns, true)) {
                    $table->string('mime_type')->nullable();
                }
                if (! in_array('extension', $columns, true)) {
                    $table->string('extension', 20)->nullable();
                }
                if (! in_array('size_bytes', $columns, true)) {
                    $table->unsignedBigInteger('size_bytes')->nullable();
                }
                if (! in_array('size', $columns, true)) {
                    $table->unsignedBigInteger('size')->nullable();
                }
                if (! in_array('uploaded_by', $columns, true)) {
                    $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive down migration for live chat data.
    }
};
