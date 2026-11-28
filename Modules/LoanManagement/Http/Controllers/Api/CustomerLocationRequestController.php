<?php

namespace Modules\LoanManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanLocationRequestService;

class CustomerLocationRequestController extends Controller
{
    public function __construct(
        protected LoanLocationRequestService $locations,
    ) {
    }

    public function pending(Request $request)
    {
        $customerId = (int)($request->user()->id ?? 0);

        return response()->json([
            'success' => true,
            'message' => 'Location requests loaded',
            'data' => $this->locations->listCustomerPending($customerId),
        ]);
    }

    public function respond(Request $request, $locationRequest)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customerId = (int)($request->user()->id ?? 0);
        $updated = $this->locations->respondAsCustomer(
            $customerId,
            (int)$locationRequest,
            $validated['decision'],
            isset($validated['latitude']) ? (float)$validated['latitude'] : null,
            isset($validated['longitude']) ? (float)$validated['longitude'] : null,
            isset($validated['accuracy']) ? (float)$validated['accuracy'] : null
        );

        return response()->json([
            'success' => true,
            'message' => $validated['decision'] === 'approve' ? 'Location shared' : 'Location request declined',
            'data' => $updated,
        ]);
    }

    public function ping(Request $request, $locationRequest)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customerId = (int)($request->user()->id ?? 0);
        $point = $this->locations->recordPing(
            $customerId,
            (int)$locationRequest,
            (float)$validated['latitude'],
            (float)$validated['longitude'],
            isset($validated['accuracy']) ? (float)$validated['accuracy'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Location updated',
            'data' => $point,
        ]);
    }

    public function stop(Request $request, $locationRequest)
    {
        $customerId = (int)($request->user()->id ?? 0);
        $updated = $this->locations->stopAsCustomer($customerId, (int)$locationRequest);

        return response()->json([
            'success' => true,
            'message' => 'Location sharing stopped',
            'data' => $updated,
        ]);
    }
}
