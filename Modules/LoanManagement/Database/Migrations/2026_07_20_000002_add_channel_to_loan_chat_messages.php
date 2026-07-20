<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_chat_messages')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_chat_messages', function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn('loan_chat_messages', 'channel')) {
                $table->string('channel', 20)->default('app')->after('message_type');
                $table->index('channel', 'lm_chat_messages_channel_idx');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive down for installed systems.
    }
};
