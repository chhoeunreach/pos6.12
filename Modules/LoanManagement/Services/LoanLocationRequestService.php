<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Modules\LoanManagement\Entities\LoanChatThread;
use Modules\LoanManagement\Entities\LoanLocationPoint;
use Modules\LoanManagement\Entities\LoanLocationRequest;

/**
 * Consent-based live location request workflow between staff/owner and customers.
 */
class LoanLocationRequestService
{
    public function __construct(
        protected BranchPermissionService $branchPerms,
        protected LoanNotificationService $notifications,
        protected LoanChatService $chat,
    ) {
    }

    public function createRequest($staff, array $payload): LoanLocationRequest
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff) && !$this->branchPerms->hasPermission($staff, 'loan.location.request')) {
            abort(403, 'You do not have permission to request customer location.');
        }

        $customerId = (int)($payload['customer_id'] ?? 0);
        if ($customerId <= 0) {
            abort(422, 'customer_id is required.');
        }

        $businessId = $this->branchPerms->getBusinessId($staff);
        $locationId = $this->branchPerms->resolveRequestedLocationId($staff, $payload['location_id'] ?? null);
        if ($locationId === null) {
            $permitted = $this->branchPerms->getPermittedLocationIds($staff);
            $locationId = is_array($permitted) && !empty($permitted) ? (int)$permitted[0] : null;
        }

        $mode = ($payload['mode'] ?? 'once') === 'live' ? 'live' : 'once';
        $duration = (int)($payload['duration_minutes'] ?? 15);
        $duration = max(5, min($duration, 120));
        $reason = isset($payload['reason']) ? trim((string)$payload['reason']) : null;
        $threadId = isset($payload['thread_id']) ? (int)$payload['thread_id'] : null;
        $loanId = isset($payload['loan_id']) ? (int)$payload['loan_id'] : null;

        return DB::connection('mysql_loan')->transaction(function () use (
            $staff,
            $businessId,
            $locationId,
            $customerId,
            $loanId,
            $threadId,
            $mode,
            $duration,
            $reason
        ) {
            LoanLocationRequest::query()
                ->where('customer_id', $customerId)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subHours(2))
                ->update(['status' => 'expired']);

            $req = LoanLocationRequest::create([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'customer_id' => $customerId,
                'requested_by_staff_id' => (int)($staff->id ?? 0),
                'loan_id' => $loanId,
                'thread_id' => $threadId,
                'mode' => $mode,
                'duration_minutes' => $duration,
                'reason' => $reason ?: 'Location verification request',
                'status' => 'pending',
                'expires_at' => now()->addMinutes($mode === 'live' ? $duration : 30),
            ]);

            if ($threadId) {
                $thread = LoanChatThread::query()->find($threadId);
                if ($thread) {
                    $label = $mode === 'live'
                        ? "Staff requested live location for {$duration} min"
                        : 'Staff requested your current location';
                    $this->chat->sendTextMessage($thread, 'staff', (int)($staff->id ?? 0), $label);
                }
            }

            $this->notifications->notifyUser(
                'customer',
                $customerId,
                'location_request',
                'Location Sharing Request',
                $req->reason ?: 'The shop staff requested to view your location.',
                [
                    'request_id' => $req->id,
                    'mode' => $req->mode,
                    'duration_minutes' => $req->duration_minutes,
                ],
                $businessId,
                $locationId
            );

            return $req;
        });
    }

    public function listCustomerPending(int $customerId)
    {
        $this->expireElapsed();

        return LoanLocationRequest::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->get();
    }

    public function respondAsCustomer(int $customerId, int $requestId, string $decision, ?float $lat = null, ?float $lng = null, ?float $accuracy = null): LoanLocationRequest
    {
        $this->expireElapsed();

        $req = LoanLocationRequest::query()->findOrFail($requestId);
        if ((int)$req->customer_id !== $customerId) {
            abort(403, 'Forbidden');
        }
        if ($req->status !== 'pending') {
            abort(422, 'Location request is no longer pending.');
        }

        if ($decision === 'reject') {
            $req->status = 'rejected';
            $req->responded_at = now();
            $req->save();

            $this->notifications->notifyUser(
                'staff',
                (int)$req->requested_by_staff_id,
                'location_request',
                'Location Request Declined',
                'Customer declined the location sharing request.',
                ['request_id' => $req->id, 'customer_id' => $customerId],
                $req->business_id,
                $req->location_id
            );

            return $req;
        }

        if ($lat === null || $lng === null) {
            abort(422, 'Latitude and longitude are required to approve.');
        }

        $req->status = $req->mode === 'live' ? 'approved' : 'stopped';
        $req->responded_at = now();
        $req->expires_at = $req->mode === 'live'
            ? now()->addMinutes($req->duration_minutes)
            : now();
        if ($req->mode === 'once') {
            $req->stopped_at = now();
        }
        $req->save();

        LoanLocationPoint::create([
            'request_id' => $req->id,
            'customer_id' => $customerId,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'recorded_at' => now(),
        ]);

        if ($req->thread_id) {
            $thread = LoanChatThread::query()->find($req->thread_id);
            if ($thread) {
                $this->chat->sendLocationMessage(
                    $thread,
                    'customer',
                    $customerId,
                    $lat,
                    $lng,
                    'Shared requested location',
                    ['location_request_id' => $req->id]
                );
            }
        }

        $this->notifications->notifyUser(
            'staff',
            (int)$req->requested_by_staff_id,
            'location_request',
            'Customer Shared Location',
            'Customer approved your location request.',
            ['request_id' => $req->id, 'customer_id' => $customerId, 'latitude' => $lat, 'longitude' => $lng],
            $req->business_id,
            $req->location_id
        );

        return $req->fresh(['points']);
    }

    public function recordPing(int $customerId, int $requestId, float $lat, float $lng, ?float $accuracy = null): LoanLocationPoint
    {
        $this->expireElapsed();

        $req = LoanLocationRequest::query()->findOrFail($requestId);
        if ((int)$req->customer_id !== $customerId) {
            abort(403, 'Forbidden');
        }
        if ($req->status !== 'approved' || ($req->expires_at && $req->expires_at->isPast())) {
            abort(422, 'Location sharing session is not active.');
        }

        return LoanLocationPoint::create([
            'request_id' => $req->id,
            'customer_id' => $customerId,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'recorded_at' => now(),
        ]);
    }

    public function stopAsCustomer(int $customerId, int $requestId): LoanLocationRequest
    {
        $req = LoanLocationRequest::query()->findOrFail($requestId);
        if ((int)$req->customer_id !== $customerId) {
            abort(403, 'Forbidden');
        }
        $req->status = 'stopped';
        $req->stopped_at = now();
        $req->save();
        return $req;
    }

    public function stopAsStaff($staff, int $requestId): LoanLocationRequest
    {
        $req = $this->getForStaff($staff, $requestId);
        $req->status = 'stopped';
        $req->stopped_at = now();
        $req->save();
        return $req;
    }

    public function getForStaff($staff, int $requestId): LoanLocationRequest
    {
        $this->expireElapsed();

        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.location.view')
            && !$this->branchPerms->hasPermission($staff, 'loan.location.request')) {
            abort(403, 'Forbidden');
        }

        $req = LoanLocationRequest::query()
            ->with(['points' => fn ($q) => $q->orderByDesc('recorded_at')->limit(50)])
            ->findOrFail($requestId);

        if (!$this->branchPerms->canAccessLocation($staff, $req->location_id)) {
            abort(403, 'Branch access denied.');
        }

        return $req;
    }

    public function listForCustomerByStaff($staff, int $customerId)
    {
        $this->expireElapsed();

        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.location.view')
            && !$this->branchPerms->hasPermission($staff, 'loan.location.request')) {
            abort(403, 'Forbidden');
        }

        $query = LoanLocationRequest::query()
            ->with(['points' => fn ($q) => $q->orderByDesc('recorded_at')->limit(10)])
            ->where('customer_id', $customerId)
            ->orderByDesc('id');

        $permitted = $this->branchPerms->getPermittedLocationIds($staff);
        if (is_array($permitted)) {
            if (empty($permitted)) {
                return collect();
            }
            $query->where(function ($q) use ($permitted) {
                $q->whereNull('location_id')->orWhereIn('location_id', $permitted);
            });
        }

        return $query->limit(20)->get();
    }

    protected function expireElapsed(): void
    {
        LoanLocationRequest::query()
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }
}
