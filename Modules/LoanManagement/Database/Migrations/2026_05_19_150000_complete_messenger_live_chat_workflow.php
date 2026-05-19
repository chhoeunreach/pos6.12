<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        $connection = $this->connection;

        if (! Schema::connection($connection)->hasTable('loan_chat_threads')) {
            Schema::connection($connection)->create('loan_chat_threads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('thread_number', 64)->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('staff_id')->nullable();
                $table->unsignedBigInteger('assigned_staff_id')->nullable();
                $table->string('assigned_team')->nullable();
                $table->unsignedBigInteger('loan_id')->nullable();
                $table->string('subject')->nullable();
                $table->string('display_name')->nullable();
                $table->string('display_subtitle')->nullable();
                $table->string('avatar_url', 2048)->nullable();
                $table->string('type', 60)->default('customer_staff');
                $table->string('status', 40)->default('open');
                $table->string('priority', 40)->default('normal');
                $table->boolean('is_group')->default(false);
                $table->boolean('is_closed')->default(false);
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_muted')->default(false);
                $table->timestamp('typing_customer_at')->nullable();
                $table->timestamp('typing_staff_at')->nullable();
                $table->timestamp('last_seen_customer_at')->nullable();
                $table->timestamp('last_seen_staff_at')->nullable();
                $table->longText('last_message')->nullable();
                $table->string('last_message_type', 50)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->string('last_sender_type', 40)->nullable();
                $table->string('last_sender_name')->nullable();
                $table->unsignedInteger('unread_customer_count')->default(0);
                $table->unsignedInteger('unread_staff_count')->default(0);
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->string('closed_reason')->nullable();
                $table->string('created_by_type', 40)->default('staff');
                $table->unsignedBigInteger('created_by_id')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['customer_id', 'status'], 'lm_chat_threads_customer_status_idx');
                $table->index(['staff_id', 'assigned_staff_id'], 'lm_chat_threads_staff_assigned_idx');
            });
        } else {
            $this->ensureThreadColumns($connection);
            $this->widenThreadEnums($connection);
        }

        if (! Schema::connection($connection)->hasTable('loan_chat_messages')) {
            Schema::connection($connection)->create('loan_chat_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('thread_id');
                $table->string('sender_type', 40);
                $table->unsignedBigInteger('sender_id');
                $table->string('sender_name_snapshot');
                $table->string('sender_avatar_snapshot', 2048)->nullable();
                $table->longText('message')->nullable();
                $table->string('message_type', 40)->default('text');
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
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_by_customer_at')->nullable();
                $table->timestamp('read_by_staff_at')->nullable();
                $table->string('reaction')->nullable();
                $table->unsignedBigInteger('reply_to_message_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('local_uuid', 80)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['thread_id', 'created_at'], 'lm_chat_messages_thread_created_idx');
            });
        } else {
            $this->ensureMessageColumns($connection);
            $this->widenMessageEnums($connection);
        }

        if (! Schema::connection($connection)->hasTable('loan_chat_participants')) {
            Schema::connection($connection)->create('loan_chat_participants', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('thread_id');
                $table->string('participant_type', 40);
                $table->unsignedBigInteger('participant_id');
                $table->string('participant_name_snapshot')->nullable();
                $table->string('participant_avatar_snapshot', 2048)->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_owner')->default(false);
                $table->timestamp('last_read_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->timestamps();
                $table->unique(['thread_id', 'participant_type', 'participant_id'], 'lm_chat_participants_unique');
            });
        } else {
            $this->ensureParticipantColumns($connection);
        }
    }

    public function down(): void
    {
        // Additive compatibility migration. Keep data intact.
    }

    protected function ensureThreadColumns(string $connection): void
    {
        $columns = Schema::connection($connection)->getColumnListing('loan_chat_threads');
        Schema::connection($connection)->table('loan_chat_threads', function (Blueprint $table) use ($columns) {
            if (! in_array('assigned_staff_id', $columns, true)) $table->unsignedBigInteger('assigned_staff_id')->nullable()->after('staff_id');
            if (! in_array('assigned_team', $columns, true)) $table->string('assigned_team')->nullable()->after('assigned_staff_id');
            if (! in_array('display_name', $columns, true)) $table->string('display_name')->nullable()->after('subject');
            if (! in_array('display_subtitle', $columns, true)) $table->string('display_subtitle')->nullable()->after('display_name');
            if (! in_array('avatar_url', $columns, true)) $table->string('avatar_url', 2048)->nullable()->after('display_subtitle');
            if (! in_array('is_group', $columns, true)) $table->boolean('is_group')->default(false)->after('priority');
            if (! in_array('is_closed', $columns, true)) $table->boolean('is_closed')->default(false)->after('is_group');
            if (! in_array('is_pinned', $columns, true)) $table->boolean('is_pinned')->default(false)->after('is_closed');
            if (! in_array('is_muted', $columns, true)) $table->boolean('is_muted')->default(false)->after('is_pinned');
            if (! in_array('typing_customer_at', $columns, true)) $table->timestamp('typing_customer_at')->nullable()->after('is_muted');
            if (! in_array('typing_staff_at', $columns, true)) $table->timestamp('typing_staff_at')->nullable()->after('typing_customer_at');
            if (! in_array('last_seen_customer_at', $columns, true)) $table->timestamp('last_seen_customer_at')->nullable()->after('typing_staff_at');
            if (! in_array('last_seen_staff_at', $columns, true)) $table->timestamp('last_seen_staff_at')->nullable()->after('last_seen_customer_at');
            if (! in_array('last_sender_type', $columns, true)) $table->string('last_sender_type', 40)->nullable()->after('last_message_at');
            if (! in_array('last_sender_name', $columns, true)) $table->string('last_sender_name')->nullable()->after('last_sender_type');
            if (! in_array('closed_reason', $columns, true)) $table->string('closed_reason')->nullable()->after('closed_by');
        });
    }

    protected function ensureMessageColumns(string $connection): void
    {
        $columns = Schema::connection($connection)->getColumnListing('loan_chat_messages');
        Schema::connection($connection)->table('loan_chat_messages', function (Blueprint $table) use ($columns) {
            if (! in_array('sender_avatar_snapshot', $columns, true)) $table->string('sender_avatar_snapshot', 2048)->nullable()->after('sender_name_snapshot');
            if (! in_array('delivered_at', $columns, true)) $table->timestamp('delivered_at')->nullable()->after('read_at');
            if (! in_array('read_by_customer_at', $columns, true)) $table->timestamp('read_by_customer_at')->nullable()->after('delivered_at');
            if (! in_array('read_by_staff_at', $columns, true)) $table->timestamp('read_by_staff_at')->nullable()->after('read_by_customer_at');
            if (! in_array('reaction', $columns, true)) $table->string('reaction')->nullable()->after('read_by_staff_at');
            if (! in_array('reply_to_message_id', $columns, true)) $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('reaction');
            if (! in_array('local_uuid', $columns, true)) $table->string('local_uuid', 80)->nullable()->after('metadata');
        });
    }

    protected function ensureParticipantColumns(string $connection): void
    {
        $columns = Schema::connection($connection)->getColumnListing('loan_chat_participants');
        Schema::connection($connection)->table('loan_chat_participants', function (Blueprint $table) use ($columns) {
            if (! in_array('participant_avatar_snapshot', $columns, true)) $table->string('participant_avatar_snapshot', 2048)->nullable()->after('participant_name_snapshot');
            if (! in_array('is_active', $columns, true)) $table->boolean('is_active')->default(true)->after('left_at');
            if (! in_array('is_owner', $columns, true)) $table->boolean('is_owner')->default(false)->after('is_active');
            if (! in_array('unread_count', $columns, true)) $table->unsignedInteger('unread_count')->default(0)->after('last_read_at');
        });
    }

    protected function widenThreadEnums(string $connection): void
    {
        try {
            DB::connection($connection)->statement("ALTER TABLE loan_chat_threads MODIFY type VARCHAR(60) NOT NULL DEFAULT 'customer_staff'");
            DB::connection($connection)->statement("ALTER TABLE loan_chat_threads MODIFY status VARCHAR(40) NOT NULL DEFAULT 'open'");
            DB::connection($connection)->statement("ALTER TABLE loan_chat_threads MODIFY priority VARCHAR(40) NOT NULL DEFAULT 'normal'");
        } catch (Throwable $e) {
            // Older databases may block ALTER without DBAL/privileges; the app avoids unsupported values where needed.
        }
    }

    protected function widenMessageEnums(string $connection): void
    {
        try {
            DB::connection($connection)->statement("ALTER TABLE loan_chat_messages MODIFY sender_type VARCHAR(40) NOT NULL");
            DB::connection($connection)->statement("ALTER TABLE loan_chat_messages MODIFY message_type VARCHAR(40) NOT NULL DEFAULT 'text'");
        } catch (Throwable $e) {
            // Keep migration non-fatal for restricted MySQL users.
        }
    }
};
