<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row table letting an admin configure the Telegram customer-chat bot from the UI
 * (System Settings > Telegram Bot) instead of editing .env directly. Values here take
 * precedence over the LOAN_CHAT_TELEGRAM_* env vars when set (see TelegramSettingsService).
 */
return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_telegram_settings')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_telegram_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('bot_token')->nullable();
            $table->string('bot_username')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->unsignedInteger('link_ttl_minutes')->nullable();
            $table->string('webhook_url')->nullable();
            $table->timestamp('webhook_registered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('loan_telegram_settings');
    }
};
