<?php

namespace Modules\LoanManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanMobilePaymentService;

class CustomerPaymentController extends Controller
{
    public function __construct(
        protected LoanMobilePaymentService $payments,
    ) {
    }

    public function index(Request $request)
    {
        $customerId = (int)($request->user()->id ?? 0);
        $items = $this->payments->listCustomerSubmissions($customerId);

        return response()->json([
            'success' => true,
            'message' => 'Payment submissions loaded',
            'data' => $items,
        ]);
    }

    public function generateKhqr(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer', 'min:1'],
            'schedule_id' => ['nullable', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $customerId = (int)($request->user()->id ?? 0);
        $submission = $this->payments->generateKhqr($customerId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'KHQR generated',
            'data' => $submission,
        ]);
    }

    public function status(Request $request, string $reference)
    {
        $customerId = (int)($request->user()->id ?? 0);
        $submission = $this->payments->getCustomerStatus($customerId, $reference);

        return response()->json([
            'success' => true,
            'message' => 'Payment status loaded',
            'data' => $submission,
        ]);
    }

    public function submitProof(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer', 'min:1'],
            'schedule_id' => ['nullable', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'transaction_ref' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $customerId = (int)($request->user()->id ?? 0);
        $submission = $this->payments->submitProof($customerId, $validated, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Payment proof submitted for verification',
            'data' => $submission,
        ]);
    }

    public function khqrWebhook(Request $request)
    {
        $expected = (string)config('services.bakong.webhook_secret', env('KHQR_WEBHOOK_SECRET', ''));
        $provided = (string)$request->header('X-Webhook-Secret', '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            abort(403, 'Invalid webhook signature.');
        }

        $validated = $request->validate([
            'reference_no' => ['required', 'string', 'max:64'],
            'transaction_ref' => ['nullable', 'string', 'max:120'],
        ]);

        $submission = $this->payments->confirmKhqrWebhook(
            $validated['reference_no'],
            $validated['transaction_ref'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'KHQR payment processed',
            'data' => $submission,
        ]);
    }
}
