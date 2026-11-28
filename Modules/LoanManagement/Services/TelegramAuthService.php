<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Entities\LoanTelegramAuthSession;

/**
 * Handles Telegram Bot Deep-Link quick registration and login for customers & guests.
 */
class TelegramAuthService
{
    public function initSession(): array
    {
        $token = Str::random(40);
        $expiresAt = now()->addMinutes(5);

        LoanTelegramAuthSession::create([
            'token' => $token,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        $botUsername = $this->resolveBotUsername();

        return [
            'token' => $token,
            'bot_deep_link' => "https://t.me/{$botUsername}?start=auth_{$token}",
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function handleWebhook(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return;
        }

        $from = $message['from'] ?? [];
        $chatId = (int)($message['chat']['id'] ?? 0);
        $tgUserId = (int)($from['id'] ?? 0);
        $tgUsername = isset($from['username']) ? (string)$from['username'] : null;
        $tgName = trim(((string)($from['first_name'] ?? '')) . ' ' . ((string)($from['last_name'] ?? '')));

        if ($chatId <= 0 || $tgUserId <= 0) {
            return;
        }

        $text = trim((string)($message['text'] ?? ''));
        if (str_starts_with($text, '/start auth_')) {
            $token = trim(substr($text, strlen('/start auth_')));
            $this->handleStartWithToken($token, $chatId, $tgUserId, $tgUsername, $tgName);
            return;
        }

        if (isset($message['contact']) && is_array($message['contact'])) {
            $contactUserId = (int)($message['contact']['user_id'] ?? $tgUserId);
            if ($contactUserId !== $tgUserId) {
                $this->sendTelegramMessage($chatId, 'Please share your own phone number to continue.');
                return;
            }
            $phone = (string)($message['contact']['phone_number'] ?? '');
            $this->handleContactShared($chatId, $tgUserId, $tgUsername, $tgName, $phone);
        }
    }

    public function checkStatus(string $token): array
    {
        $session = LoanTelegramAuthSession::query()
            ->where('token', $token)
            ->firstOrFail();

        if ($session->status !== 'verified' && $session->expires_at && $session->expires_at->isPast()) {
            $session->status = 'expired';
            $session->save();
        }

        if ($session->status !== 'verified') {
            return [
                'status' => $session->status,
                'token' => null,
                'user' => null,
                'is_new_customer' => false,
            ];
        }

        $customer = $this->findCustomerById((int)$session->customer_id);

        return [
            'status' => 'verified',
            'token' => $session->api_token,
            'user' => $customer,
            'is_new_customer' => (bool)$session->is_new_customer,
        ];
    }

    public function resolveBotUsername(): string
    {
        $envVal = (string)config('services.telegram.bot_username', env('TELEGRAM_BOT_USERNAME', ''));
        if (trim($envVal) !== '') {
            return ltrim(trim($envVal), '@');
        }
        $settingsVal = class_exists(TelegramSettingsService::class) ? TelegramSettingsService::botUsername() : '';
        $resolved = trim($settingsVal) !== '' ? trim($settingsVal) : 'kneayerng_loan_bot';

        return ltrim($resolved, '@');
    }

    public function resolveBotToken(): string
    {
        $envVal = (string)config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        if (trim($envVal) !== '') {
            return trim($envVal);
        }

        return class_exists(TelegramSettingsService::class) ? trim(TelegramSettingsService::botToken()) : '';
    }

    public function resolveWebhookSecret(): string
    {
        $envVal = (string)config('services.telegram.webhook_secret', env('TELEGRAM_WEBHOOK_SECRET', ''));
        if (trim($envVal) !== '') {
            return trim($envVal);
        }

        return class_exists(TelegramSettingsService::class) ? trim(TelegramSettingsService::webhookSecret()) : '';
    }

    protected function handleStartWithToken(
        string $token,
        int $chatId,
        int $tgUserId,
        ?string $tgUsername,
        string $tgName
    ): void {
        $session = LoanTelegramAuthSession::query()
            ->where('token', $token)
            ->first();

        if (!$session || ($session->expires_at && $session->expires_at->isPast()) || $session->status === 'verified') {
            $this->sendTelegramMessage($chatId, 'This login link has expired. Please return to the app and try again.');
            return;
        }

        $session->telegram_user_id = $tgUserId;
        $session->telegram_username = $tgUsername;
        $session->telegram_name = $tgName;

        $existing = $this->findCustomerByTelegram($tgUserId, $chatId);
        if ($existing) {
            $this->finalizeVerification($session, $existing, false, $chatId);
            return;
        }

        $session->status = 'awaiting_phone';
        $session->save();

        $this->sendTelegramMessage($chatId, 'Tap the button below to verify your phone number and sign in to KNEAYERNG.', [
            'keyboard' => [[
                ['text' => 'Share Phone Number', 'request_contact' => true],
            ]],
            'one_time_keyboard' => true,
            'resize_keyboard' => true,
        ]);
    }

    protected function handleContactShared(
        int $chatId,
        int $tgUserId,
        ?string $tgUsername,
        string $tgName,
        string $rawPhone
    ): void {
        $session = LoanTelegramAuthSession::query()
            ->where('telegram_user_id', $tgUserId)
            ->where('status', 'awaiting_phone')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$session) {
            $this->sendTelegramMessage($chatId, 'No active login session found. Please tap Login with Telegram in the app again.');
            return;
        }

        $phone = $this->normalizePhone($rawPhone);
        $customer = $this->findCustomerByPhone($phone);
        $isNew = false;

        if (!$customer) {
            $customer = $this->createLeadCustomer($phone, $tgUserId, $chatId, $tgUsername, $tgName);
            $isNew = true;
        } else {
            $this->linkTelegramToCustomer((int)$customer->id, $tgUserId, $chatId, $tgUsername);
        }

        $session->phone = $phone;
        $this->finalizeVerification($session, $customer, $isNew, $chatId);
    }

    protected function finalizeVerification(
        LoanTelegramAuthSession $session,
        object $customer,
        bool $isNew,
        int $chatId
    ): void {
        $apiToken = $this->issueCustomerToken((int)$customer->id);

        $session->customer_id = (int)$customer->id;
        $session->is_new_customer = $isNew;
        $session->api_token = $apiToken;
        $session->status = 'verified';
        $session->verified_at = now();
        $session->save();

        $this->sendTelegramMessage($chatId, 'Signed in successfully! You can return to the KNEAYERNG app now.', [
            'remove_keyboard' => true,
        ]);
    }

    protected function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
        if (str_starts_with($digits, '855')) {
            $digits = '0' . substr($digits, 3);
        }
        return $digits;
    }

    protected function findCustomerByTelegram(int $tgUserId, int $chatId): ?object
    {
        try {
            $schema = Schema::connection('mysql_loan');
            $hasTgUserId = $schema->hasColumn('loan_customers', 'telegram_user_id');
            $hasTgChatId = $schema->hasColumn('loan_customers', 'telegram_chat_id');

            $query = DB::connection('mysql_loan')->table('loan_customers')->whereNull('deleted_at');
            $query->where(function ($q) use ($hasTgUserId, $hasTgChatId, $tgUserId, $chatId) {
                if ($hasTgUserId) {
                    $q->orWhere('telegram_user_id', $tgUserId);
                }
                if ($hasTgChatId) {
                    $q->orWhere('telegram_chat_id', (string)$chatId);
                }
            });

            if (!$hasTgUserId && !$hasTgChatId) {
                return null;
            }

            return $query->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function findCustomerByPhone(string $phone): ?object
    {
        if ($phone === '') {
            return null;
        }
        try {
            return DB::connection('mysql_loan')
                ->table('loan_customers')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)
                        ->orWhere('login_phone', $phone);
                })
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function findCustomerById(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }
        try {
            return DB::connection('mysql_loan')
                ->table('loan_customers')
                ->where('id', $id)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function createLeadCustomer(string $phone, int $tgUserId, int $chatId, ?string $tgUsername, string $tgName): object
    {
        $now = now();
        $name = $tgName !== '' ? $tgName : ($tgUsername ? '@' . $tgUsername : $phone);
        $code = 'TG-' . $now->format('ymd') . '-' . strtoupper(Str::random(6));

        $schema = Schema::connection('mysql_loan');
        $row = [
            'name' => $name,
            'phone' => $phone,
            'login_phone' => $phone,
            'status' => 'active',
            'can_login' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($schema->hasColumn('loan_customers', 'customer_code')) {
            $row['customer_code'] = $code;
        }
        if ($schema->hasColumn('loan_customers', 'customer_type')) {
            $row['customer_type'] = 'lead';
        }
        if ($schema->hasColumn('loan_customers', 'telegram_user_id')) {
            $row['telegram_user_id'] = $tgUserId;
        }
        if ($schema->hasColumn('loan_customers', 'telegram_chat_id')) {
            $row['telegram_chat_id'] = (string)$chatId;
        }
        if ($schema->hasColumn('loan_customers', 'telegram_username')) {
            $row['telegram_username'] = $tgUsername;
        }
        if ($schema->hasColumn('loan_customers', 'telegram_linked_at')) {
            $row['telegram_linked_at'] = $now;
        }

        $id = (int)DB::connection('mysql_loan')->table('loan_customers')->insertGetId($row);

        return (object)[
            'id' => $id,
            'customer_code' => $code,
            'name' => $name,
            'phone' => $phone,
            'telegram_user_id' => $tgUserId,
            'telegram_chat_id' => (string)$chatId,
            'telegram_username' => $tgUsername,
        ];
    }

    protected function linkTelegramToCustomer(int $customerId, int $tgUserId, int $chatId, ?string $tgUsername): void
    {
        try {
            $schema = Schema::connection('mysql_loan');
            $update = ['updated_at' => now()];

            if ($schema->hasColumn('loan_customers', 'telegram_user_id')) {
                $update['telegram_user_id'] = $tgUserId;
            }
            if ($schema->hasColumn('loan_customers', 'telegram_chat_id')) {
                $update['telegram_chat_id'] = (string)$chatId;
            }
            if ($schema->hasColumn('loan_customers', 'telegram_username')) {
                $update['telegram_username'] = $tgUsername;
            }
            if ($schema->hasColumn('loan_customers', 'telegram_linked_at')) {
                $update['telegram_linked_at'] = now();
            }

            DB::connection('mysql_loan')
                ->table('loan_customers')
                ->where('id', $customerId)
                ->update($update);
        } catch (\Throwable $e) {
            // Non-fatal if optional columns differ.
        }
    }

    protected function issueCustomerToken(int $customerId): string
    {
        try {
            $model = LoanCustomer::find($customerId);
            if ($model && method_exists($model, 'createToken')) {
                $tokenResult = $model->createToken('telegram-auth');
                return (string)($tokenResult->accessToken ?? ($tokenResult->plainTextToken ?? ''));
            }
        } catch (\Throwable $e) {
            Log::warning('TelegramAuthService token issue fallback: ' . $e->getMessage());
        }

        return Str::random(64);
    }

    protected function sendTelegramMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $botToken = $this->resolveBotToken();
        if ($botToken === '') {
            return;
        }

        try {
            $body = [
                'chat_id' => $chatId,
                'text' => $text,
            ];
            if ($replyMarkup !== null) {
                $body['reply_markup'] = $replyMarkup;
            }
            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", $body);
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed: ' . $e->getMessage());
        }
    }
}
