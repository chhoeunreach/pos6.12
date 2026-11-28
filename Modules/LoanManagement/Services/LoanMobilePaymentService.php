<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\LoanManagement\Entities\LoanPaymentSubmission;

/**
 * Handles customer dynamic KHQR generation, manual payment proof upload,
 * and branch-scoped staff approval synced with POS 6.12 payments.
 *
 * Kept separate from pos6.12's LoanPaymentService to preserve POS ledger hooks.
 */
class LoanMobilePaymentService
{
    public function __construct(
        protected FileUploadService $files,
        protected BranchPermissionService $branchPerms,
        protected LoanNotificationService $notifications,
    ) {
    }

    public function generateKhqr(int $customerId, array $payload): LoanPaymentSubmission
    {
        $loanId = (int)($payload['loan_id'] ?? 0);
        $scheduleId = isset($payload['schedule_id']) ? (int)$payload['schedule_id'] : null;
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $currency = strtoupper((string)($payload['currency'] ?? 'USD'));
        if ($loanId <= 0 || $amount <= 0) {
            abort(422, 'Valid loan_id and amount are required.');
        }

        $loanMeta = $this->resolveLoanOwnership($customerId, $loanId);
        $reference = 'KHQR-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
        $qrString = $this->buildEmvKhqr($amount, $currency, $reference);

        return LoanPaymentSubmission::create([
            'business_id' => $loanMeta['business_id'],
            'location_id' => $loanMeta['location_id'],
            'customer_id' => $customerId,
            'loan_id' => $loanId,
            'schedule_id' => $scheduleId,
            'reference_no' => $reference,
            'payment_method' => 'khqr',
            'amount' => $amount,
            'currency' => $currency,
            'qr_string' => $qrString,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function submitProof(int $customerId, array $payload, UploadedFile $file): LoanPaymentSubmission
    {
        $loanId = (int)($payload['loan_id'] ?? 0);
        $scheduleId = isset($payload['schedule_id']) ? (int)$payload['schedule_id'] : null;
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $currency = strtoupper((string)($payload['currency'] ?? 'USD'));
        if ($loanId <= 0 || $amount <= 0) {
            abort(422, 'Valid loan_id and amount are required.');
        }

        $loanMeta = $this->resolveLoanOwnership($customerId, $loanId);
        $upload = $this->files->uploadChatAttachment($file, 'payment_proof');
        $reference = 'PROOF-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));

        return LoanPaymentSubmission::create([
            'business_id' => $loanMeta['business_id'],
            'location_id' => $loanMeta['location_id'],
            'customer_id' => $customerId,
            'loan_id' => $loanId,
            'schedule_id' => $scheduleId,
            'reference_no' => $reference,
            'payment_method' => 'proof',
            'amount' => $amount,
            'currency' => $currency,
            'proof_file_id' => $upload['id'],
            'proof_file_url' => $upload['url'],
            'bank_name' => $payload['bank_name'] ?? null,
            'transaction_ref' => $payload['transaction_ref'] ?? null,
            'note' => $payload['note'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function getCustomerStatus(int $customerId, string $referenceNo): LoanPaymentSubmission
    {
        $submission = LoanPaymentSubmission::query()
            ->where('reference_no', $referenceNo)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if ($submission->payment_method === 'khqr'
            && $submission->status === 'pending'
            && $submission->expires_at
            && $submission->expires_at->isPast()) {
            $submission->status = 'expired';
            $submission->save();
        }

        return $submission;
    }

    public function listCustomerSubmissions(int $customerId)
    {
        return LoanPaymentSubmission::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function listStaffSubmissions($staff, ?string $status = null, ?int $locationId = null)
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.payment.approve_proof')
            && !$this->branchPerms->hasPermission($staff, 'loan.payment.receive')) {
            abort(403, 'Forbidden');
        }

        $query = LoanPaymentSubmission::query()->orderByDesc('id');
        $businessId = $this->branchPerms->getBusinessId($staff);
        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $requestedLoc = $this->branchPerms->resolveRequestedLocationId($staff, $locationId);
        if ($requestedLoc !== null) {
            $query->where('location_id', $requestedLoc);
        } else {
            $permitted = $this->branchPerms->getPermittedLocationIds($staff);
            if (is_array($permitted)) {
                if (empty($permitted)) {
                    return collect();
                }
                $query->whereIn('location_id', $permitted);
            }
        }

        if ($status !== null && trim($status) !== '') {
            $query->where('status', trim($status));
        }

        return $query->limit(100)->get();
    }

    public function reviewSubmission($staff, int $submissionId, string $decision, ?string $reviewNote = null): LoanPaymentSubmission
    {
        if (!$this->branchPerms->isOwnerOrAdmin($staff)
            && !$this->branchPerms->hasPermission($staff, 'loan.payment.approve_proof')) {
            abort(403, 'You do not have permission to review payment proofs.');
        }

        $submission = LoanPaymentSubmission::query()->findOrFail($submissionId);
        if (!$this->branchPerms->canAccessLocation($staff, $submission->location_id)) {
            abort(403, 'Branch access denied.');
        }
        if ($submission->status !== 'pending') {
            abort(422, 'Submission has already been processed.');
        }

        return DB::connection('mysql_loan')->transaction(function () use ($staff, $submission, $decision, $reviewNote) {
            $submission->status = $decision === 'approve' ? 'approved' : 'rejected';
            $submission->reviewed_by = (int)($staff->id ?? 0);
            $submission->reviewed_at = now();
            $submission->review_note = $reviewNote;

            if ($decision === 'approve') {
                $submission->pos_payment_id = $this->recordPosLoanPayment($submission, (int)($staff->id ?? 0));
            }

            $submission->save();

            $this->notifications->notifyUser(
                'customer',
                (int)$submission->customer_id,
                'payment',
                $decision === 'approve' ? 'Payment Approved' : 'Payment Proof Rejected',
                $decision === 'approve'
                    ? "Your payment of {$submission->currency} {$submission->amount} ({$submission->reference_no}) was approved."
                    : ('Payment proof was declined: ' . ($reviewNote ?: 'Please re-check your receipt.')),
                [
                    'submission_id' => $submission->id,
                    'loan_id' => $submission->loan_id,
                    'reference_no' => $submission->reference_no,
                    'status' => $submission->status,
                ],
                $submission->business_id,
                $submission->location_id
            );

            return $submission;
        });
    }

    public function confirmKhqrWebhook(string $referenceNo, ?string $transactionRef = null): LoanPaymentSubmission
    {
        $submission = LoanPaymentSubmission::query()
            ->where('reference_no', $referenceNo)
            ->firstOrFail();

        if (in_array($submission->status, ['paid', 'approved'], true)) {
            return $submission;
        }

        return DB::connection('mysql_loan')->transaction(function () use ($submission, $transactionRef) {
            $submission->status = 'paid';
            if ($transactionRef) {
                $submission->transaction_ref = $transactionRef;
            }
            $submission->reviewed_at = now();
            $submission->pos_payment_id = $this->recordPosLoanPayment($submission, null);
            $submission->save();

            $this->notifications->notifyUser(
                'customer',
                (int)$submission->customer_id,
                'payment',
                'KHQR Payment Confirmed',
                "Payment of {$submission->currency} {$submission->amount} received successfully.",
                [
                    'submission_id' => $submission->id,
                    'loan_id' => $submission->loan_id,
                    'reference_no' => $submission->reference_no,
                ],
                $submission->business_id,
                $submission->location_id
            );

            return $submission;
        });
    }

    protected function resolveLoanOwnership(int $customerId, int $loanId): array
    {
        try {
            $loan = DB::connection('mysql_loan')
                ->table('loans')
                ->where('id', $loanId)
                ->first();
            if ($loan) {
                if (isset($loan->customer_id) && (int)$loan->customer_id !== $customerId) {
                    abort(403, 'Loan does not belong to this customer.');
                }
                return [
                    'business_id' => isset($loan->business_id) ? (int)$loan->business_id : null,
                    'location_id' => isset($loan->business_location_id)
                        ? (int)$loan->business_location_id
                        : (isset($loan->location_id) ? (int)$loan->location_id : null),
                ];
            }
        } catch (\Throwable $e) {
            // Loan table may vary across deployments; fall back gracefully.
        }

        return ['business_id' => null, 'location_id' => null];
    }

    protected function recordPosLoanPayment(LoanPaymentSubmission $submission, ?int $staffId): ?int
    {
        try {
            if (!DB::connection('mysql_loan')->getSchemaBuilder()->hasTable('loan_payments')) {
                return null;
            }

            return (int)DB::connection('mysql_loan')->table('loan_payments')->insertGetId([
                'loan_id' => $submission->loan_id,
                'schedule_id' => $submission->schedule_id,
                'customer_id' => $submission->customer_id,
                'amount' => $submission->amount,
                'payment_method' => $submission->payment_method === 'khqr' ? 'aba_khqr' : 'bank_transfer',
                'reference_no' => $submission->reference_no,
                'payment_date' => now()->toDateString(),
                'note' => $submission->note,
                'created_by' => $staffId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildEmvKhqr(float $amount, string $currency, string $reference): string
    {
        $merchantAccount = (string)config('services.bakong.account_id', env('BAKONG_ACCOUNT_ID', 'kneayerng@aba'));
        $merchantName = substr((string)config('app.name', 'KNEAYERNG'), 0, 25);
        $currencyCode = $currency === 'KHR' ? '116' : '840';
        $amountFormatted = $currency === 'KHR'
            ? (string)((int)round($amount))
            : number_format($amount, 2, '.', '');

        $tlv = fn (string $id, string $val): string =>
            $id . str_pad((string)strlen($val), 2, '0', STR_PAD_LEFT) . $val;

        $payload = ''
            . $tlv('00', '01')
            . $tlv('01', '12')
            . $tlv('29', $tlv('00', $merchantAccount))
            . $tlv('52', '5999')
            . $tlv('53', $currencyCode)
            . $tlv('54', $amountFormatted)
            . $tlv('58', 'KH')
            . $tlv('59', $merchantName)
            . $tlv('60', 'Phnom Penh')
            . $tlv('62', $tlv('01', substr($reference, 0, 25)))
            . '6304';

        return $payload . $this->crc16($payload);
    }

    protected function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
