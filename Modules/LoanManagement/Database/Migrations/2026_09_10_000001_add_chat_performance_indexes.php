<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'mysql_loan';

    public function up(): void
    {
        $this->addIndexIfMissing('loan_chat_messages', ['thread_id', 'id'], 'lm_chat_messages_thread_id_idx');
        $this->addIndexIfMissing('loan_chat_messages', ['thread_id', 'sender_type', 'is_read'], 'lm_chat_messages_thread_sender_read_idx');
        $this->addIndexIfMissing('loan_telegram_chat_messages', ['thread_id', 'id'], 'lm_tg_messages_thread_id_idx');
        $this->addIndexIfMissing('loan_telegram_chat_messages', ['thread_id', 'sender_type', 'is_read'], 'lm_tg_messages_thread_sender_read_idx');
        $this->addIndexIfMissing('loan_telegram_chat_threads', ['status', 'last_message_at', 'id'], 'lm_tg_threads_status_last_idx');
        $this->addIndexIfMissing('loan_telegram_chat_threads', ['status', 'updated_at'], 'lm_tg_threads_status_updated_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('loan_chat_messages', 'lm_chat_messages_thread_sender_read_idx');
        $this->dropIndexIfExists('loan_chat_messages', 'lm_chat_messages_thread_id_idx');
        $this->dropIndexIfExists('loan_telegram_chat_messages', 'lm_tg_messages_thread_sender_read_idx');
        $this->dropIndexIfExists('loan_telegram_chat_messages', 'lm_tg_messages_thread_id_idx');
        $this->dropIndexIfExists('loan_telegram_chat_threads', 'lm_tg_threads_status_updated_idx');
        $this->dropIndexIfExists('loan_telegram_chat_threads', 'lm_tg_threads_status_last_idx');
    }

    protected function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        if (! Schema::connection($this->connection)->hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::connection($this->connection)->table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    protected function dropIndexIfExists(string $table, string $name): void
    {
        if (! Schema::connection($this->connection)->hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }

        Schema::connection($this->connection)->table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    protected function indexExists(string $table, string $name): bool
    {
        $database = DB::connection($this->connection)->getDatabaseName();
        $indexes = DB::connection($this->connection)->select(
            'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, $table, $name]
        );

        return ! empty($indexes);
    }
};
