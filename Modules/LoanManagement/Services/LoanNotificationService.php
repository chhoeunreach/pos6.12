<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\LoanManagement\Entities\LoanDeviceToken;
use Modules\LoanManagement\Entities\LoanNotification;

/**
 * Handles in-app notifications, device token registration, and optional FCM / broadcast dispatch.
 */
class LoanNotificationService
{
    public function registerToken(
        string $userType,
        int $userId,
        string $fcmToken,
        ?int $businessId = null,
        ?string $deviceId = null,
        string $platform = 'android'
    ): LoanDeviceToken {
        return LoanDeviceToken::query()->updateOrCreate(
            [
                'user_type' => $userType,
                'user_id' => $userId,
                'fcm_token' => $fcmToken,
            ],
            [
                'business_id' => $businessId,
                'device_id' => $deviceId,
                'platform' => $platform,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    public function deactivateToken(string $userType, int $userId, ?string $fcmToken = null): void
    {
        $query = LoanDeviceToken::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId);

        if ($fcmToken !== null && trim($fcmToken) !== '') {
            $query->where('fcm_token', trim($fcmToken));
        }

        $query->update(['is_active' => false]);
    }

    public function notifyUser(
        string $recipientType,
        int $recipientId,
        string $category,
        string $title,
        string $body,
        array $data = [],
        ?int $businessId = null,
        ?int $locationId = null
    ): ?LoanNotification {
        if ($recipientId <= 0) {
            return null;
        }

        $notification = LoanNotification::create([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'is_read' => false,
        ]);

        $this->dispatchPush($recipientType, $recipientId, $notification);

        return $notification;
    }

    public function listForRecipient(string $recipientType, int $recipientId, int $limit = 50)
    {
        return LoanNotification::query()
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 200)))
            ->get();
    }

    public function unreadCount(string $recipientType, int $recipientId): int
    {
        return LoanNotification::query()
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->where('is_read', false)
            ->count();
    }

    public function markRead(string $recipientType, int $recipientId, int $notificationId): void
    {
        $item = LoanNotification::query()
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->findOrFail($notificationId);

        if (!$item->is_read) {
            $item->is_read = true;
            $item->read_at = now();
            $item->save();
        }
    }

    public function markAllRead(string $recipientType, int $recipientId): void
    {
        LoanNotification::query()
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    protected function dispatchPush(string $recipientType, int $recipientId, LoanNotification $notification): void
    {
        $tokens = LoanDeviceToken::query()
            ->where('user_type', $recipientType)
            ->where('user_id', $recipientId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->all();

        if (empty($tokens)) {
            return;
        }

        $serverKey = (string)config('services.fcm.server_key', env('FCM_SERVER_KEY', ''));
        if ($serverKey === '') {
            return;
        }

        try {
            Http::timeout(5)->withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'priority' => 'high',
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                'data' => array_merge($notification->data ?? [], [
                    'notification_id' => $notification->id,
                    'category' => $notification->category,
                ]),
            ]);

            $notification->pushed_at = now();
            $notification->save();
        } catch (\Throwable $e) {
            Log::warning('FCM push failed: ' . $e->getMessage());
        }
    }
}
