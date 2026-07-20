<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the effective Telegram bot configuration: a DB row saved from System Settings >
 * Telegram Bot takes precedence over the LOAN_CHAT_TELEGRAM_* env vars, so an admin can
 * configure/rotate the bot without touching .env or restarting the app.
 */
class TelegramSettingsService
{
    protected const CONNECTION = 'mysql_loan';
    protected const TABLE = 'loan_telegram_settings';

    public static function get(): array
    {
        $row = self::row();

        return [
            'bot_token' => self::pick($row->bot_token ?? null, config('loanmanagement.telegram.bot_token', '')),
            'bot_username' => self::pick($row->bot_username ?? null, config('loanmanagement.telegram.bot_username', '')),
            'webhook_secret' => self::pick($row->webhook_secret ?? null, config('loanmanagement.telegram.webhook_secret', '')),
            'link_ttl_minutes' => (int) self::pick($row->link_ttl_minutes ?? null, config('loanmanagement.telegram.link_ttl_minutes', 15)),
            'webhook_url' => $row->webhook_url ?? null,
            'webhook_registered_at' => $row->webhook_registered_at ?? null,
        ];
    }

    public static function botToken(): string
    {
        return (string) self::get()['bot_token'];
    }

    public static function botUsername(): string
    {
        return (string) self::get()['bot_username'];
    }

    public static function webhookSecret(): string
    {
        return (string) self::get()['webhook_secret'];
    }

    public static function linkTtlMinutes(): int
    {
        return (int) self::get()['link_ttl_minutes'];
    }

    /**
     * Save admin-provided settings. Blank fields keep the previously saved value (so the UI
     * can show a masked token without forcing the admin to re-paste it on every save).
     */
    public static function save(array $data): void
    {
        if (! self::tableReady()) {
            return;
        }

        $existing = self::row();
        $payload = [
            'bot_token' => $data['bot_token'] !== '' ? $data['bot_token'] : ($existing->bot_token ?? null),
            'bot_username' => $data['bot_username'] ?? ($existing->bot_username ?? null),
            'webhook_secret' => $data['webhook_secret'] !== '' ? $data['webhook_secret'] : ($existing->webhook_secret ?? null),
            'link_ttl_minutes' => $data['link_ttl_minutes'] ?? ($existing->link_ttl_minutes ?? null),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::connection(self::CONNECTION)->table(self::TABLE)->where('id', 1)->update($payload);
        } else {
            DB::connection(self::CONNECTION)->table(self::TABLE)->insert(array_merge($payload, [
                'id' => 1,
                'created_at' => now(),
            ]));
        }
    }

    public static function markWebhookRegistered(string $url): void
    {
        if (! self::tableReady()) {
            return;
        }

        DB::connection(self::CONNECTION)->table(self::TABLE)->updateOrInsert(
            ['id' => 1],
            ['webhook_url' => $url, 'webhook_registered_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    protected static function pick($dbValue, $fallback)
    {
        if ($dbValue !== null && $dbValue !== '') {
            return $dbValue;
        }

        return $fallback;
    }

    protected static function row()
    {
        if (! self::tableReady()) {
            return null;
        }

        return DB::connection(self::CONNECTION)->table(self::TABLE)->where('id', 1)->first();
    }

    protected static function tableReady(): bool
    {
        return Schema::connection(self::CONNECTION)->hasTable(self::TABLE);
    }
}
