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

                if (! in_array('avatar_url', $columns, true)) {
                    $table->string('avatar_url', 2048)->nullable()->after('priority');
                }
                if (! in_array('is_pinned', $columns, true)) {
                    $table->boolean('is_pinned')->default(false)->after('avatar_url');
                }
                if (! in_array('is_muted', $columns, true)) {
                    $table->boolean('is_muted')->default(false)->after('is_pinned');
                }
                if (! in_array('last_seen_customer_at', $columns, true)) {
                    $table->timestamp('last_seen_customer_at')->nullable()->after('unread_staff_count');
                }
                if (! in_array('last_seen_staff_at', $columns, true)) {
                    $table->timestamp('last_seen_staff_at')->nullable()->after('last_seen_customer_at');
                }
                if (! in_array('typing_customer_at', $columns, true)) {
                    $table->timestamp('typing_customer_at')->nullable()->after('last_seen_staff_at');
                }
                if (! in_array('typing_staff_at', $columns, true)) {
                    $table->timestamp('typing_staff_at')->nullable()->after('typing_customer_at');
                }
            });
        }

        if (Schema::connection($conn)->hasTable('loan_chat_messages')) {
            Schema::connection($conn)->table('loan_chat_messages', function (Blueprint $table) use ($conn) {
                $columns = Schema::connection($conn)->getColumnListing('loan_chat_messages');

                if (! in_array('delivered_at', $columns, true)) {
                    $table->timestamp('delivered_at')->nullable()->after('local_uuid');
                }
                if (! in_array('read_by_customer_at', $columns, true)) {
                    $table->timestamp('read_by_customer_at')->nullable()->after('delivered_at');
                }
                if (! in_array('read_by_staff_at', $columns, true)) {
                    $table->timestamp('read_by_staff_at')->nullable()->after('read_by_customer_at');
                }
                if (! in_array('reaction', $columns, true)) {
                    $table->string('reaction', 80)->nullable()->after('read_by_staff_at');
                }
                if (! in_array('reply_to_message_id', $columns, true)) {
                    $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('reaction');
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive down for installed POS systems.
    }
};
