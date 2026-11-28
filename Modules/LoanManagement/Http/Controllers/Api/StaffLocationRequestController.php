<?php

namespace Modules\LoanManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanLocationRequestService;

class StaffLocationRequestController extends Controller
{
    public function __construct(
        protected LoanLocationRequestService $locations,
    ) {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'loan_id' => ['nullable', 'integer', 'min:1'],
            'thread_id' => ['nullable', 'integer', 'min:1'],
            'mode' => ['nullable', 'in:once,live'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:120'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $created = $this->locations->createRequest($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Location request sent to customer',
            'data' => $created,
        ]);
    }

    public function show(Request $request, $locationRequest)
    {
        $item = $this->locations->getForStaff($request->user(), (int)$locationRequest);

        return response()->json([
            'success' => true,
            'message' => 'Location request loaded',
            'data' => $item,
        ]);
    }

    public function customerHistory(Request $request, $customer)
    {
        $items = $this->locations->listForCustomerByStaff($request->user(), (int)$customer);

        return response()->json([
            'success' => true,
            'message' => 'Customer location requests loaded',
            'data' => $items,
        ]);
    }

    public function stop(Request $request, $locationRequest)
    {
        $updated = $this->locations->stopAsStaff($request->user(), (int)$locationRequest);

        return response()->json([
            'success' => true,
            'message' => 'Location sharing stopped',
            'data' => $updated,
        ]);
    }
}
