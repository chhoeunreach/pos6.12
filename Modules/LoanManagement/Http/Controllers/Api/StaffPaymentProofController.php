<?php

namespace Modules\LoanManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanMobilePaymentService;

class StaffPaymentProofController extends Controller
{
    public function __construct(
        protected LoanMobilePaymentService $payments,
    ) {
    }

    public function index(Request $request)
    {
        $status = $request->query('status') ? (string)$request->query('status') : null;
        $locationId = $request->query('location_id') ? (int)$request->query('location_id') : null;
        $items = $this->payments->listStaffSubmissions($request->user(), $status, $locationId);

        return response()->json([
            'success' => true,
            'message' => 'Payment submissions loaded',
            'data' => $items,
        ]);
    }

    public function review(Request $request, $submission)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->payments->reviewSubmission(
            $request->user(),
            (int)$submission,
            $validated['decision'],
            $validated['review_note'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => $validated['decision'] === 'approve' ? 'Payment proof approved' : 'Payment proof rejected',
            'data' => $updated,
        ]);
    }
}
