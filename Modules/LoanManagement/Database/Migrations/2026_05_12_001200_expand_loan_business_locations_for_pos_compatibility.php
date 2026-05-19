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
            $this->addColumnIfMissing($table, 'main_business_id', fn () => $table->unsignedBigInteger('main_business_id')->nullable()->after('id'));
            $this->addColumnIfMissing($table, 'main_location_id', fn () => $table->unsignedBigInteger('main_location_id')->nullable()->after('main_business_id'));
            $this->addColumnIfMissing($table, 'name', fn () => $table->string('name')->nullable()->after('main_location_id'));
            $this->addColumnIfMissing($table, 'location_code', fn () => $table->string('location_code')->nullable()->after('name'));
            $this->addColumnIfMissing($table, 'address', fn () => $table->text('address')->nullable()->after('location_code'));
            $this->addColumnIfMissing($table, 'phone', fn () => $table->string('phone', 50)->nullable()->after('address'));
            $this->addColumnIfMissing($table, 'logo_path', fn () => $table->string('logo_path')->nullable()->after('phone'));
            $this->addColumnIfMissing($table, 'payment_qr_path', fn () => $table->string('payment_qr_path')->nullable()->after('logo_path'));
            $this->addColumnIfMissing($table, 'telegram_qr_path', fn () => $table->string('telegram_qr_path')->nullable()->after('payment_qr_path'));
            $this->addColumnIfMissing($table, 'telegram_chat_id', fn () => $table->string('telegram_chat_id')->nullable()->after('telegram_qr_path'));
            $this->addColumnIfMissing($table, 'telegram_notify_payment', fn () => $table->boolean('telegram_notify_payment')->default(false)->after('telegram_chat_id'));
            $this->addColumnIfMissing($table, 'telegram_notify_installment', fn () => $table->boolean('telegram_notify_installment')->default(false)->after('telegram_notify_payment'));
            $this->addColumnIfMissing($table, 'invoice_scheme_id', fn () => $table->unsignedBigInteger('invoice_scheme_id')->nullable()->after('telegram_notify_installment'));
            $this->addColumnIfMissing($table, 'status', fn () => $table->string('status', 20)->default('active')->after('invoice_scheme_id'));
            $this->addColumnIfMissing($table, 'synced_at', fn () => $table->timestamp('synced_at')->nullable()->after('updated_at'));
        });
    }

    public function down(): void
    {
        // Keep non-destructive.
    }

    protected function addColumnIfMissing(Blueprint $table, string $name, callable $creator): void
    {
        if (! Schema::connection($this->connection)->hasColumn($this->table, $name)) {
            $creator();
        }
    }
};
