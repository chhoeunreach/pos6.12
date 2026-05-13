<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\LoanManagement\Http\Requests\CustomerLocationUpdateRequest;
use Modules\LoanManagement\Http\Requests\CustomerTrackingToggleRequest;
use Modules\LoanManagement\Services\CustomerLocationTrackingService;

class CustomerLocationTrackingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected CustomerLocationTrackingService $trackingService)
    {
    }

    public function update(CustomerLocationUpdateRequest $request)
    {
        $customer = auth('customer_loan_api')->user();
        if (! $customer->allow_gps_tracking) {
            return $this->fail('GPS tracking disabled', 403, (object) []);
        }

        $payload = $request->validated();
        $this->trackingService->updateRealtimeLocation($customer, $payload);
        $this->trackingService->updateLatestLocation($customer, $payload);

        $latest = $this->trackingService->getLatestLocation((int) $customer->id);
        return $this->ok('Location updated', $latest ?: (object) []);
    }

    public function status()
    {
        $customer = auth('customer_loan_api')->user();
        $latest = $this->trackingService->getLatestLocation((int) $customer->id);
        return $this->ok('Status loaded', [
            'allow_gps_tracking' => (bool) $customer->allow_gps_tracking,
            'gps_tracking_started_at' => $customer->gps_tracking_started_at,
            'gps_tracking_stopped_at' => $customer->gps_tracking_stopped_at,
            'latest_location' => $latest ?: (object) [],
        ]);
    }

    public function enable(CustomerTrackingToggleRequest $request)
    {
        $customer = auth('customer_loan_api')->user();
        $this->trackingService->enableTracking($customer, $request->input('note'));
        return $this->ok('GPS tracking enabled', (object) []);
    }

    public function disable(CustomerTrackingToggleRequest $request)
    {
        $customer = auth('customer_loan_api')->user();
        $this->trackingService->disableTracking($customer, $request->input('note'));
        return $this->ok('GPS tracking disabled', (object) []);
    }
}
