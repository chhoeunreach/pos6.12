<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\LoanManagement\Http\Requests\CustomerLocationUpdateRequest;
use Modules\LoanManagement\Http\Requests\CustomerTrackingToggleRequest;
use Modules\LoanManagement\Services\CustomerLocationTrackingService;

class CustomerLocationTrackingController extends Controller
{
    public function __construct(protected CustomerLocationTrackingService $trackingService)
    {
    }

    public function update(CustomerLocationUpdateRequest $request)
    {
        $customer = auth('customer_loan_api')->user();
        if (! $customer->allow_gps_tracking) {
            return response()->json(['success' => false, 'message' => 'GPS tracking disabled', 'data' => (object) []], 403);
        }

        $payload = $request->validated();
        $this->trackingService->updateRealtimeLocation($customer, $payload);
        $this->trackingService->updateLatestLocation($customer, $payload);

        return response()->json(['success' => true, 'message' => 'Location updated', 'data' => (object) []]);
    }

    public function status()
    {
        $customer = auth('customer_loan_api')->user();
        $latest = $this->trackingService->getLatestLocation((int) $customer->id);
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'allow_gps_tracking' => (bool) $customer->allow_gps_tracking,
                'gps_tracking_started_at' => $customer->gps_tracking_started_at,
                'gps_tracking_stopped_at' => $customer->gps_tracking_stopped_at,
                'latest_location' => $latest,
            ],
        ]);
    }

    public function enable(CustomerTrackingToggleRequest $request)
    {
        $customer = auth('customer_loan_api')->user();
        $this->trackingService->enableTracking($customer, $request->input('note'));
        return response()->json(['success' => true, 'message' => 'GPS tracking enabled', 'data' => (object) []]);
    }

    public function disable(CustomerTrackingToggleRequest $request)
    {
        $customer = auth('customer_loan_api')->user();
        $this->trackingService->disableTracking($customer, $request->input('note'));
        return response()->json(['success' => true, 'message' => 'GPS tracking disabled', 'data' => (object) []]);
    }
}

