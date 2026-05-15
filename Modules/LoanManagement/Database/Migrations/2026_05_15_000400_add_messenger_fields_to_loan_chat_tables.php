<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_chat_threads')) {
            Schema::connection($this->connection)->table('loan_chat_threads', function (Blueprint $table) {
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_threads', 'avatar_url')) {
                    $table->string('avatar_url')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_threads', 'is_pinned')) {
                    $table->boolean('is_pinned')->default(false);
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_threads', 'is_muted')) {
                    $table->boolean('is_muted')->default(false);
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_threads', 'last_seen_customer_at')) {
                    $table->timestamp('last_seen_customer_at')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_threads', 'last_seen_staff_at')) {
                    $table->timestamp('last_seen_staff_at')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_threads', 'typing_customer_at')) {
                    $table->timestamp('typing_customer_at')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_threads', 'typing_staff_at')) {
                    $table->timestamp('typing_staff_at')->nullable();
                }
            });
        }

        if (Schema::connection($this->connection)->hasTable('loan_chat_messages')) {
            Schema::connection($this->connection)->table('loan_chat_messages', function (Blueprint $table) {
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_messages', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_messages', 'read_by_customer_at')) {
                    $table->timestamp('read_by_customer_at')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_messages', 'read_by_staff_at')) {
                    $table->timestamp('read_by_staff_at')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_messages', 'reaction')) {
                    $table->string('reaction')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_messages', 'reply_to_message_id')) {
                    $table->unsignedBigInteger('reply_to_message_id')->nullable();
                }
                if (! Schema::connection($this->connection)->hasColumn('loan_chat_messages', 'local_uuid')) {
                    $table->string('local_uuid')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $this->dropExistingColumns('loan_chat_threads', [
            'avatar_url',
            'is_pinned',
            'is_muted',
            'last_seen_customer_at',
            'last_seen_staff_at',
            'typing_customer_at',
            'typing_staff_at',
        ]);

        $this->dropExistingColumns('loan_chat_messages', [
            'delivered_at',
            'read_by_customer_at',
            'read_by_staff_at',
            'reaction',
            'reply_to_message_id',
            'local_uuid',
        ]);
    }

    protected function dropExistingColumns(string $tableName, array $columns): void
    {
        if (! Schema::connection($this->connection)->hasTable($tableName)) {
            return;
        }

        $existing = [];
        foreach ($columns as $column) {
            if (Schema::connection($this->connection)->hasColumn($tableName, $column)) {
                $existing[] = $column;
            }
        }

        if ($existing === []) {
            return;
        }

        Schema::connection($this->connection)->table($tableName, function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
