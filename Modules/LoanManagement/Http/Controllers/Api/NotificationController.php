<?php

namespace Modules\LoanManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Services\BranchPermissionService;
use Modules\LoanManagement\Services\LoanNotificationService;

class NotificationController extends Controller
{
    public function __construct(
        protected LoanNotificationService $notifications,
        protected BranchPermissionService $branchPerms,
    ) {
    }

    protected function resolveActor(Request $request): array
    {
        $user = $request->user();
        $isCustomer = $user instanceof LoanCustomer
            || str_contains((string)$request->route()?->getPrefix(), 'customer');

        return [$isCustomer ? 'customer' : 'staff', (int)($user->id ?? 0), $user];
    }

    public function index(Request $request)
    {
        [$type, $id] = $this->resolveActor($request);
        $limit = (int)$request->query('limit', 50);

        return response()->json([
            'success' => true,
            'message' => 'Notifications loaded',
            'data' => [
                'unread_count' => $this->notifications->unreadCount($type, $id),
                'items' => $this->notifications->listForRecipient($type, $id, $limit),
            ],
        ]);
    }

    public function markRead(Request $request, $notification)
    {
        [$type, $id] = $this->resolveActor($request);
        $this->notifications->markRead($type, $id, (int)$notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => (object)[],
        ]);
    }

    public function markAllRead(Request $request)
    {
        [$type, $id] = $this->resolveActor($request);
        $this->notifications->markAllRead($type, $id);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => (object)[],
        ]);
    }

    public function registerToken(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
            'device_id' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'in:android,ios,web'],
        ]);

        [$type, $id, $user] = $this->resolveActor($request);
        $token = $this->notifications->registerToken(
            $type,
            $id,
            $validated['fcm_token'],
            $this->branchPerms->getBusinessId($user),
            $validated['device_id'] ?? null,
            $validated['platform'] ?? 'android'
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token registered',
            'data' => $token,
        ]);
    }

    public function deactivateToken(Request $request)
    {
        [$type, $id] = $this->resolveActor($request);
        $this->notifications->deactivateToken($type, $id, $request->input('fcm_token'));

        return response()->json([
            'success' => true,
            'message' => 'Device token deactivated',
            'data' => (object)[],
        ]);
    }
}
