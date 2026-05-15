<?php

namespace Modules\LoanManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class TestChatSchemaCommand extends Command
{
    protected $signature = 'loan-management:test-chat-schema';

    protected $description = 'Check LoanManagement Messenger chat schema columns on mysql_loan';

    public function handle(): int
    {
        $connection = 'mysql_loan';
        $checks = [
            'loan_chat_threads' => [
                'avatar_url',
                'is_pinned',
                'is_muted',
                'last_seen_customer_at',
                'last_seen_staff_at',
                'typing_customer_at',
                'typing_staff_at',
            ],
            'loan_chat_messages' => [
                'message_type',
                'file_id',
                'file_url',
                'file_name',
                'file_mime',
                'file_size',
                'audio_duration_seconds',
                'metadata',
                'delivered_at',
                'read_by_customer_at',
                'read_by_staff_at',
                'local_uuid',
            ],
            'loan_files' => [
                'file_type',
                'original_name',
                'mime_type',
                'size_bytes',
                'extension',
                'path',
                'url',
                'uploaded_by',
                'storage_provider',
                'disk',
            ],
        ];

        try {
            $failed = false;
            foreach ($checks as $table => $columns) {
                if (! Schema::connection($connection)->hasTable($table)) {
                    $this->error("FAIL {$table}: table missing");
                    $failed = true;
                    continue;
                }

                foreach ($columns as $column) {
                    if (Schema::connection($connection)->hasColumn($table, $column)) {
                        $this->info("PASS {$table}.{$column}");
                    } else {
                        $this->error("FAIL {$table}.{$column}");
                        $failed = true;
                    }
                }
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FAIL mysql_loan connection: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
