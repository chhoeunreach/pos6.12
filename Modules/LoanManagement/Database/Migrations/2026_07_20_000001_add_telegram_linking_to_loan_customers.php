<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_customers', function (Blueprint $table) {
            $columns = Schema::connection($this->connection)->getColumnListing('loan_customers');

            if (! in_array('telegram_chat_id', $columns, true)) {
                $table->string('telegram_chat_id')->nullable()->unique()->after('telegram');
            }
            if (! in_array('telegram_username', $columns, true)) {
                $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            }
            if (! in_array('telegram_linked_at', $columns, true)) {
                $table->timestamp('telegram_linked_at')->nullable()->after('telegram_username');
            }
            if (! in_array('telegram_link_token', $columns, true)) {
                $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_linked_at');
            }
            if (! in_array('telegram_link_token_expires_at', $columns, true)) {
                $table->timestamp('telegram_link_token_expires_at')->nullable()->after('telegram_link_token');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive down for installed systems.
    }
};
