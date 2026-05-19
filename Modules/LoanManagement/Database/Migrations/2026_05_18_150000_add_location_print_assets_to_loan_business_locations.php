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
            $this->addColumnIfMissing($table, 'logo_path', fn () => $table->string('logo_path')->nullable());
            $this->addColumnIfMissing($table, 'payment_qr_path', fn () => $table->string('payment_qr_path')->nullable());
            $this->addColumnIfMissing($table, 'telegram_qr_path', fn () => $table->string('telegram_qr_path')->nullable());
            $this->addColumnIfMissing($table, 'telegram_chat_id', fn () => $table->string('telegram_chat_id')->nullable());
            $this->addColumnIfMissing($table, 'telegram_notify_payment', fn () => $table->boolean('telegram_notify_payment')->default(false));
            $this->addColumnIfMissing($table, 'telegram_notify_installment', fn () => $table->boolean('telegram_notify_installment')->default(false));
        });
    }

    public function down(): void
    {
        // Keep uploaded asset references for data safety.
    }

    protected function addColumnIfMissing(Blueprint $table, string $name, callable $creator): void
    {
        if (! Schema::connection($this->connection)->hasColumn($this->table, $name)) {
            $creator();
        }
    }
};
