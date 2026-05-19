<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_business_locations';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'telegram_payment_chat_id', fn () => $table->string('telegram_payment_chat_id')->nullable()->after('telegram_chat_id'));
            $this->addColumnIfMissing($table, 'telegram_installment_chat_id', fn () => $table->string('telegram_installment_chat_id')->nullable()->after('telegram_payment_chat_id'));
        });
    }

    public function down(): void
    {
        // Keep notification routing data for safety.
    }

    protected function addColumnIfMissing(Blueprint $table, string $name, callable $creator): void
    {
        if (! Schema::connection($this->connection)->hasColumn($this->table, $name)) {
            $creator();
        }
    }
};
