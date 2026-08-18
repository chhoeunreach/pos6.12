<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\LoanManagement\Entities\LoanTelegramChatThread;
use Modules\LoanManagement\Services\TelegramChatService;
use Modules\LoanManagement\Services\TelegramSettingsService;

class StaffMobileActionController extends Controller
{
    use ApiResponseTrait;

    protected string $conn = 'mysql_loan';

    public function receivePayment(Request $request)
    {
        $this->ensurePaymentTypeColumn();

        $data = $request->validate([
            'loan_id' => 'required|integer|min:1',
            'customer_id' => 'required|integer|min:1',
            'currency' => 'required|in:USD,KHR',
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'nullable|date',
            'note' => 'nullable|string',
            'pay_off' => 'nullable|boolean',
            'schedule_ids' => 'nullable|array',
            'schedule_ids.*' => 'integer|min:1',
            'details' => 'required|array|min:1',
            'details.*.method' => 'required|string|max:30',
            'details.*.amount' => 'required|numeric|min:0.01',
            'details.*.currency' => 'nullable|in:USD,KHR',
            'details.*.exchange_rate' => 'nullable|numeric|min:0.0001',
            'details.*.transaction_no' => 'nullable|string|max:255',
            'payment_docs' => 'nullable|array',
            'payment_docs.*' => 'nullable|string',
        ]);

        $result = DB::connection($this->conn)->transaction(function () use ($data) {
            $loan = DB::connection($this->conn)->table('loans')->where('id', $data['loan_id'])->first();
            if (! $loan) {
                throw new \RuntimeException('Loan not found');
            }

            $payAt = ! empty($data['paid_at']) ? $data['paid_at'] : now()->toDateTimeString();
            $amount = (float) $data['amount'];

            $paymentPayload = [
                'loan_id' => $data['loan_id'],
                'payment_type' => 'monthly',
                'schedule_id' => count((array) ($data['schedule_ids'] ?? [])) === 1 ? (int) $data['schedule_ids'][0] : null,
                'customer_id' => $data['customer_id'],
                'amount' => $amount,
                'total_paid' => $amount,
                'total_paid_base' => $amount,
                'paid_at' => $payAt,
                'paid_date' => substr($payAt, 0, 10),
                'channel' => 'mobile',
                'status' => 'confirmed',
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'payment_ref_no' => 'PMT-'.strtoupper(Str::random(10)),
                'received_by' => auth()->id(),
                'received_by_name_snapshot' => trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? ''))),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $paymentId = DB::connection($this->conn)->table('loan_payments')->insertGetId($this->safeColumns('loan_payments', $paymentPayload));
            $paymentDocIds = [];
            foreach ((array) ($data['payment_docs'] ?? []) as $index => $document) {
                if (! is_string($document) || trim($document) === '') {
                    continue;
                }

                $fileId = $this->storeMobilePaymentDoc(
                    $document,
                    $paymentId,
                    'payment-doc-'.$paymentId.'-'.($index + 1)
                );
                if ($fileId) {
                    $paymentDocIds[] = $fileId;
                }
            }

            $totalDetail = 0.0;
            foreach ($data['details'] as $detail) {
                $dAmount = (float) $detail['amount'];
                $rate = (float) ($detail['exchange_rate'] ?? 1);
                $dCurrency = (string) ($detail['currency'] ?? $data['currency']);
                $amountBase = $dCurrency === 'KHR' ? ($dAmount / max($rate, 0.0001)) : $dAmount;
                $totalDetail += $dAmount;

                $detailPayload = [
                    'payment_id' => $paymentId,
                    'method' => $detail['method'],
                    'amount' => $dAmount,
                    'transaction_no' => $detail['transaction_no'] ?? null,
                    'meta_json' => json_encode([
                        'currency' => $dCurrency,
                        'exchange_rate' => $rate,
                        'amount_base' => round($amountBase, 2),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                DB::connection($this->conn)->table('loan_payment_details')->insert($this->safeColumns('loan_payment_details', $detailPayload));
            }

            $remaining = $amount;
            if (Schema::connection($this->conn)->hasTable('loan_payment_schedules')) {
                $schedules = DB::connection($this->conn)->table('loan_payment_schedules')
                    ->where('loan_id', $data['loan_id'])
                    ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
                    ->when(! empty($data['schedule_ids']), fn ($query) => $query->whereIn('id', $data['schedule_ids']))
                    ->orderBy('due_date')
                    ->orderBy('id')
                    ->get();

                if (! empty($data['schedule_ids']) && $schedules->isEmpty()) {
                    throw new \RuntimeException('Selected payment schedule was not found.');
                }

                foreach ($schedules as $s) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $due = (float) ($s->balance_amount ?? $s->amount_balance ?? $s->schedule_amount ?? $s->amount_due ?? 0);
                    if ($due <= 0) {
                        continue;
                    }
                    $applied = min($remaining, $due);
                    if ($due > $applied && round($due - $applied, 2) <= 0.02) {
                        $applied = $due;
                    }
                    $existingPaid = (float) ($s->paid_amount ?? $s->amount_paid ?? 0);
                    $newPaid = round($existingPaid + $applied, 2);
                    $newBalance = max(0, round($due - $applied, 2));
                    $status = $newBalance <= 0 ? 'paid' : 'partial';

                    DB::connection($this->conn)->table('loan_payment_schedules')->where('id', $s->id)->update($this->safeColumns('loan_payment_schedules', [
                        'amount_paid' => $newPaid,
                        'paid_amount' => $newPaid,
                        'amount_balance' => $newBalance,
                        'balance_amount' => $newBalance,
                        'status' => $status,
                        'paid_at' => $newBalance <= 0 ? $payAt : null,
                        'updated_at' => now(),
                    ]));
                    $remaining -= $applied;
                }
            }

            $loanFresh = DB::connection($this->conn)->table('loans')->where('id', $data['loan_id'])->first();

            $paymentAmountColumn = 'amount';
            if (Schema::connection($this->conn)->hasColumn('loan_payments', 'total_paid_base')) {
                $paymentAmountColumn = 'total_paid_base';
            } elseif (Schema::connection($this->conn)->hasColumn('loan_payments', 'total_paid')) {
                $paymentAmountColumn = 'total_paid';
            }
            $newPaidAmount = (float) DB::connection($this->conn)
                ->table('loan_payments')
                ->where('loan_id', $data['loan_id'])
                ->sum($paymentAmountColumn);

            $scheduleBalance = 0.0;
            $hasScheduleBalance = false;
            if (Schema::connection($this->conn)->hasTable('loan_payment_schedules')) {
                $balanceQuery = DB::connection($this->conn)->table('loan_payment_schedules')->where('loan_id', $data['loan_id']);
                if (Schema::connection($this->conn)->hasColumn('loan_payment_schedules', 'balance_amount')) {
                    $scheduleBalance = (float) $balanceQuery->sum('balance_amount');
                    $hasScheduleBalance = true;
                } elseif (Schema::connection($this->conn)->hasColumn('loan_payment_schedules', 'amount_balance')) {
                    $scheduleBalance = (float) $balanceQuery->sum('amount_balance');
                    $hasScheduleBalance = true;
                }
            }

            if ($hasScheduleBalance) {
                $newBalanceAmount = $scheduleBalance;
            } else {
                $principal = (float) ($loanFresh->principal_amount ?? $loanFresh->total_payable_amount ?? $loanFresh->total_amount ?? 0);
                $newBalanceAmount = max(0, $principal - $newPaidAmount);
            }

            DB::connection($this->conn)->table('loans')->where('id', $data['loan_id'])->update($this->safeColumns('loans', [
                'paid_amount' => $newPaidAmount,
                'balance_amount' => $newBalanceAmount,
                'status' => $newBalanceAmount <= 0 ? 'completed' : ($loanFresh->status ?? 'active'),
                'updated_at' => now(),
            ]));

            return [
                'payment_id' => $paymentId,
                'receipt_no' => $paymentPayload['payment_ref_no'],
                'loan_id' => (int) $data['loan_id'],
                'customer_id' => (int) $data['customer_id'],
                'currency' => $data['currency'],
                'amount' => $this->money($amount),
                'detail_amount_total' => $this->money($totalDetail),
                'paid_at' => $payAt,
                'payment_doc_ids' => $paymentDocIds,
            ];
        });

        return $this->ok('Payment received successfully', $result);
    }

    public function loanPayments(Request $request, int $loanId)
    {
        if (! Schema::connection($this->conn)->hasTable('loan_payments')) {
            return $this->ok('Payments loaded', [
                'payments' => [],
                'deposit_payments' => [],
                'collection_payments' => [],
            ]);
        }

        $payments = DB::connection($this->conn)->table('loan_payments')
            ->where('loan_id', $loanId)
            ->orderByDesc($this->paymentDateColumn())
            ->orderByDesc('id')
            ->get()
            ->map(fn ($payment) => $this->paymentPayload($payment))
            ->values()
            ->all();

        $depositTypes = ['loan', 'initial', 'down_payment', 'downpayment', 'deposit'];
        $depositPayments = array_values(array_filter($payments, fn ($payment) => in_array($payment['payment_type'], $depositTypes, true)));
        $collectionPayments = array_values(array_filter($payments, fn ($payment) => ! in_array($payment['payment_type'], $depositTypes, true)));

        return $this->ok('Payments loaded', [
            'payments' => $payments,
            'deposit_payments' => $depositPayments,
            'collection_payments' => $collectionPayments,
        ]);
    }

    public function updatePayment(Request $request, int $paymentId)
    {
        $payment = $this->paymentRow($paymentId);
        if (! $payment) {
            return $this->fail('Payment not found', 404, (object) []);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_date' => 'nullable|date',
            'method' => 'nullable|string|max:100',
            'schedule_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:1000',
        ]);

        $oldAmount = $this->paymentAmount($payment);
        $newAmount = round((float) $data['amount'], 2);
        $oldScheduleId = (int) ($payment->schedule_id ?? 0);
        $newScheduleId = ! empty($data['schedule_id']) ? (int) $data['schedule_id'] : null;
        $paidDate = ! empty($data['paid_date']) ? $data['paid_date'] : substr((string) ($payment->{$this->paymentDateColumn()} ?? now()->toDateString()), 0, 10);
        $paidAt = $paidDate.' '.now()->format('H:i:s');
        $method = trim((string) ($data['method'] ?? ''));

        DB::connection($this->conn)->transaction(function () use ($paymentId, $payment, $data, $oldAmount, $newAmount, $oldScheduleId, $newScheduleId, $paidDate, $paidAt, $method) {
            DB::connection($this->conn)->table('loan_payments')->where('id', $paymentId)->update($this->safeColumns('loan_payments', [
                'schedule_id' => $newScheduleId,
                'amount' => $newAmount,
                'total_paid' => $newAmount,
                'total_paid_base' => $newAmount,
                'payment_method_snapshot' => $method !== '' ? $method : null,
                'channel' => $method !== '' ? $method : ($payment->channel ?? 'mobile'),
                'reference_number' => trim((string) ($data['reference_number'] ?? '')) ?: null,
                'paid_date' => $paidDate,
                'paid_at' => $paidAt,
                'status' => trim((string) ($data['status'] ?? 'confirmed')) ?: 'confirmed',
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'updated_at' => now(),
            ]));

            if (Schema::connection($this->conn)->hasTable('loan_payment_details')) {
                $detail = DB::connection($this->conn)->table('loan_payment_details')->where('payment_id', $paymentId)->orderBy('id')->first();
                $detailPayload = $this->safeColumns('loan_payment_details', [
                    'method' => $method !== '' ? $method : ($payment->channel ?? 'cash'),
                    'payment_method_snapshot' => $method !== '' ? $method : null,
                    'amount' => $newAmount,
                    'amount_base' => $newAmount,
                    'transaction_no' => trim((string) ($data['reference_number'] ?? '')) ?: null,
                    'reference_number' => trim((string) ($data['reference_number'] ?? '')) ?: null,
                    'note' => trim((string) ($data['note'] ?? '')) ?: null,
                    'updated_at' => now(),
                ]);
                if ($detail) {
                    DB::connection($this->conn)->table('loan_payment_details')->where('id', $detail->id)->update($detailPayload);
                } else {
                    DB::connection($this->conn)->table('loan_payment_details')->insert($detailPayload + $this->safeColumns('loan_payment_details', [
                        'payment_id' => $paymentId,
                        'created_at' => now(),
                    ]));
                }
            }

            if ($oldScheduleId > 0 && $oldScheduleId !== (int) $newScheduleId) {
                $this->adjustSchedulePayment($oldScheduleId, -$oldAmount, $paidAt);
                if ($newScheduleId) {
                    $this->adjustSchedulePayment($newScheduleId, $newAmount, $paidAt);
                }
            } elseif ($newScheduleId) {
                $this->adjustSchedulePayment($newScheduleId, $newAmount - $oldAmount, $paidAt);
            }
        });

        $this->refreshLoanTotals((int) $payment->loan_id);
        $updated = $this->paymentRow($paymentId);

        return $this->ok('Payment updated', $updated ? $this->paymentPayload($updated) : (object) []);
    }

    public function deletePayment(Request $request, int $paymentId)
    {
        $payment = $this->paymentRow($paymentId);
        if (! $payment) {
            return $this->fail('Payment not found', 404, (object) []);
        }

        DB::connection($this->conn)->transaction(function () use ($paymentId, $payment) {
            if (! empty($payment->schedule_id)) {
                $this->adjustSchedulePayment((int) $payment->schedule_id, -$this->paymentAmount($payment), now()->toDateTimeString());
            }

            if (Schema::connection($this->conn)->hasTable('loan_payment_details')) {
                DB::connection($this->conn)->table('loan_payment_details')->where('payment_id', $paymentId)->delete();
            }
            if (Schema::connection($this->conn)->hasTable('loan_files')) {
                DB::connection($this->conn)->table('loan_files')
                    ->where('fileable_type', \Modules\LoanManagement\Entities\LoanPayment::class)
                    ->where('fileable_id', $paymentId)
                    ->delete();
            }

            DB::connection($this->conn)->table('loan_payments')->where('id', $paymentId)->delete();
        });

        $this->refreshLoanTotals((int) $payment->loan_id);

        return $this->ok('Payment deleted', ['id' => $paymentId, 'loan_id' => (int) $payment->loan_id]);
    }

    public function telegramConnectLink(Request $request, int $loanId)
    {
        $loan = $this->loanRow($loanId);
        if (! $loan || empty($loan->customer_id)) {
            return $this->fail('Loan customer is missing', 404, (object) []);
        }

        $customer = $this->customerRow((int) $loan->customer_id);
        if (! $customer) {
            return $this->fail('Customer not found', 404, (object) []);
        }
        if (! empty($customer->telegram_chat_id)) {
            return $this->ok('Customer is already connected to Telegram', [
                'connected' => true,
                'telegram_chat_id' => (string) $customer->telegram_chat_id,
                'telegram_username' => (string) ($customer->telegram_username ?? ''),
            ]);
        }

        $botUsername = trim(TelegramSettingsService::botUsername());
        if ($botUsername === '') {
            return $this->fail('Telegram bot is not configured yet. Set it under System Settings > Telegram Bot.', 422, (object) []);
        }

        $token = Str::random(40);
        $expiresAt = now()->addMinutes(TelegramSettingsService::linkTtlMinutes());

        DB::connection($this->conn)->table('loan_customers')->where('id', (int) $customer->id)->update($this->safeColumns('loan_customers', [
            'telegram_link_token' => $token,
            'telegram_link_token_expires_at' => $expiresAt,
            'updated_at' => now(),
        ]));

        return $this->ok('Telegram connect link created', [
            'connected' => false,
            'link' => 'https://t.me/'.$botUsername.'?start='.$token,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function sendTelegramMessage(Request $request, int $loanId, TelegramChatService $chatService)
    {
        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $loan = $this->loanRow($loanId);
        if (! $loan || empty($loan->customer_id)) {
            return $this->fail('Loan customer is missing', 404, (object) []);
        }

        $customer = $this->customerRow((int) $loan->customer_id);
        if (! $customer) {
            return $this->fail('Customer not found', 404, (object) []);
        }
        if (empty($customer->telegram_chat_id)) {
            return $this->fail('Customer is not connected to Telegram. Generate a connect link first.', 422, [
                'connected' => false,
            ]);
        }

        $thread = $chatService->findOrCreateThread((int) $customer->id);
        $senderType = $this->isAdminUser() ? 'admin' : 'staff';
        $message = $chatService->sendTextMessage(
            $thread,
            $senderType,
            (int) auth()->id(),
            trim((string) $data['message'])
        );

        return $this->ok('Telegram message sent', [
            'thread' => $chatService->formatThread(LoanTelegramChatThread::query()->find($thread->id) ?: $thread),
            'message' => $chatService->formatMessage($message),
        ]);
    }

    public function staffLocation(Request $request)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'speed' => 'nullable|numeric',
            'heading' => 'nullable|numeric',
            'battery_level' => 'nullable|numeric',
            'device_id' => 'nullable|string|max:100',
            'app_version' => 'nullable|string|max:50',
            'recorded_at' => 'nullable|date',
            'loan_id' => 'nullable|integer',
        ]);

        $payload = [
            'staff_id' => (int) auth()->id(),
            'staff_name_snapshot' => trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? ''))),
            'loan_id' => $data['loan_id'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'speed' => $data['speed'] ?? null,
            'heading' => $data['heading'] ?? null,
            'battery_level' => $data['battery_level'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection($this->conn)->table('loan_staff_locations')->insert($this->safeColumns('loan_staff_locations', $payload));
        DB::connection($this->conn)->table('loan_staff_location_latest')->updateOrInsert(
            ['staff_id' => (int) auth()->id()],
            $this->safeColumns('loan_staff_location_latest', $payload)
        );

        $latest = DB::connection($this->conn)->table('loan_staff_location_latest')->where('staff_id', (int) auth()->id())->first();
        return $this->ok('Staff location updated', $latest ?: (object) []);
    }

    public function collectionVisit(Request $request)
    {
        $data = $request->validate([
            'loan_id' => 'required|integer|min:1',
            'customer_id' => 'required|integer|min:1',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address_snapshot' => 'nullable|string|max:500',
            'result' => 'nullable|string|max:50',
            'note' => 'nullable|string',
            'visited_at' => 'nullable|date',
            'visit_photo_file_id' => 'nullable|integer',
        ]);

        $payload = [
            'loan_id' => $data['loan_id'],
            'customer_id' => $data['customer_id'],
            'collector_id' => auth()->id(),
            'collector_name_snapshot' => trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? ''))),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'address_snapshot' => $data['address_snapshot'] ?? null,
            'result' => $data['result'] ?? 'pending',
            'status' => $data['result'] ?? 'pending',
            'note' => $data['note'] ?? null,
            'visited_at' => $data['visited_at'] ?? now()->toDateTimeString(),
            'visit_photo_file_id' => $data['visit_photo_file_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::connection($this->conn)->table('loan_collection_visits')->insertGetId($this->safeColumns('loan_collection_visits', $payload));
        $visit = DB::connection($this->conn)->table('loan_collection_visits')->where('id', $id)->first();

        return $this->ok('Collection visit saved', $visit ?: (object) []);
    }

    protected function safeColumns(string $table, array $payload): array
    {
        $columns = Schema::connection($this->conn)->hasTable($table)
            ? Schema::connection($this->conn)->getColumnListing($table)
            : [];
        return array_intersect_key($payload, array_flip($columns));
    }

    protected function paymentRow(int $paymentId)
    {
        if (! Schema::connection($this->conn)->hasTable('loan_payments')) {
            return null;
        }

        return DB::connection($this->conn)->table('loan_payments')->where('id', $paymentId)->first();
    }

    protected function loanRow(int $loanId)
    {
        if (! Schema::connection($this->conn)->hasTable('loans')) {
            return null;
        }

        return DB::connection($this->conn)->table('loans')->where('id', $loanId)->first();
    }

    protected function customerRow(int $customerId)
    {
        if (! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return null;
        }

        return DB::connection($this->conn)->table('loan_customers')->where('id', $customerId)->first();
    }

    protected function isAdminUser(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return method_exists($user, 'can') && (
            $user->can('loan_management.chat.admin')
            || $user->can('superadmin')
        );
    }

    protected function paymentPayload($payment): array
    {
        $dateColumn = $this->paymentDateColumn();
        $amount = $this->paymentAmount($payment);
        $method = (string) ($payment->payment_method_snapshot ?? $payment->method ?? $payment->channel ?? '');
        $reference = (string) ($payment->reference_number ?? $payment->payment_ref_no ?? $payment->receipt_number ?? '');

        return [
            'id' => (int) ($payment->id ?? 0),
            'loan_id' => (int) ($payment->loan_id ?? 0),
            'customer_id' => (int) ($payment->customer_id ?? 0),
            'schedule_id' => (int) ($payment->schedule_id ?? 0),
            'payment_type' => (string) ($payment->payment_type ?? 'monthly'),
            'receipt_number' => (string) ($payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #'.($payment->id ?? ''))),
            'paid_date' => (string) ($payment->{$dateColumn} ?? $payment->paid_at ?? ''),
            'payment_date' => (string) ($payment->{$dateColumn} ?? $payment->paid_at ?? ''),
            'amount' => $this->money($amount),
            'method' => $method,
            'payment_method' => $method,
            'reference_number' => $reference,
            'reference_no' => $reference,
            'status' => (string) ($payment->status ?? 'confirmed'),
            'note' => (string) ($payment->note ?? ''),
        ];
    }

    protected function paymentDateColumn(): string
    {
        return Schema::connection($this->conn)->hasColumn('loan_payments', 'paid_date') ? 'paid_date' : 'paid_at';
    }

    protected function paymentAmountColumn(): string
    {
        if (Schema::connection($this->conn)->hasColumn('loan_payments', 'total_paid_base')) {
            return 'total_paid_base';
        }
        if (Schema::connection($this->conn)->hasColumn('loan_payments', 'total_paid')) {
            return 'total_paid';
        }
        return 'amount';
    }

    protected function paymentAmount($payment): float
    {
        return round((float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0), 2);
    }

    protected function adjustSchedulePayment(int $scheduleId, float $diff, string $paidAt): void
    {
        if (! Schema::connection($this->conn)->hasTable('loan_payment_schedules')) {
            return;
        }

        $schedule = DB::connection($this->conn)->table('loan_payment_schedules')->where('id', $scheduleId)->first();
        if (! $schedule) {
            return;
        }

        $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? 0);
        if ($due <= 0) {
            $due = (float) ($schedule->principal_amount ?? $schedule->principal_due ?? 0)
                + (float) ($schedule->interest_amount ?? $schedule->interest_due ?? 0);
        }
        $oldPaid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
        $newPaid = max(0, $oldPaid + $diff);
        $newBalance = max(0, $due - $newPaid);
        if ($newBalance > 0 && $newBalance <= 0.02) {
            $newBalance = 0.0;
            $newPaid = $due;
        }

        DB::connection($this->conn)->table('loan_payment_schedules')->where('id', $scheduleId)->update($this->safeColumns('loan_payment_schedules', [
            'paid_amount' => $newPaid,
            'amount_paid' => $newPaid,
            'balance_amount' => $newBalance,
            'amount_balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'pending'),
            'paid_at' => $newBalance <= 0 ? $paidAt : null,
            'updated_at' => now(),
        ]));
    }

    protected function refreshLoanTotals(int $loanId): void
    {
        if (! Schema::connection($this->conn)->hasTable('loans')) {
            return;
        }

        $loan = DB::connection($this->conn)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            return;
        }

        $amountColumn = $this->paymentAmountColumn();
        $paid = (float) DB::connection($this->conn)->table('loan_payments')->where('loan_id', $loanId)->sum($amountColumn);
        $balance = null;
        if (Schema::connection($this->conn)->hasTable('loan_payment_schedules')) {
            if (Schema::connection($this->conn)->hasColumn('loan_payment_schedules', 'balance_amount')) {
                $balance = (float) DB::connection($this->conn)->table('loan_payment_schedules')->where('loan_id', $loanId)->sum('balance_amount');
            } elseif (Schema::connection($this->conn)->hasColumn('loan_payment_schedules', 'amount_balance')) {
                $balance = (float) DB::connection($this->conn)->table('loan_payment_schedules')->where('loan_id', $loanId)->sum('amount_balance');
            }
        }

        if ($balance === null) {
            $principal = (float) ($loan->principal_amount ?? $loan->total_payable_amount ?? $loan->total_amount ?? 0);
            $balance = max(0, $principal - $paid);
        }

        DB::connection($this->conn)->table('loans')->where('id', $loanId)->update($this->safeColumns('loans', [
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'last_payment_amount' => $paid > 0 ? $this->lastPaymentAmount($loanId) : null,
            'last_payment_date' => $paid > 0 ? $this->lastPaymentDate($loanId) : null,
            'status' => $balance <= 0 ? 'completed' : ($loan->status === 'completed' ? 'active' : ($loan->status ?? 'active')),
            'updated_at' => now(),
        ]));
    }

    protected function lastPaymentAmount(int $loanId): ?float
    {
        $row = DB::connection($this->conn)->table('loan_payments')->where('loan_id', $loanId)->orderByDesc($this->paymentDateColumn())->orderByDesc('id')->first();
        return $row ? $this->paymentAmount($row) : null;
    }

    protected function lastPaymentDate(int $loanId): ?string
    {
        $row = DB::connection($this->conn)->table('loan_payments')->where('loan_id', $loanId)->orderByDesc($this->paymentDateColumn())->orderByDesc('id')->first();
        return $row ? (string) ($row->{$this->paymentDateColumn()} ?? null) : null;
    }

    protected function storeMobilePaymentDoc(string $dataUri, int $paymentId, string $namePrefix): ?int
    {
        $dataUri = trim($dataUri);
        if ($dataUri === '' || $paymentId <= 0 || ! Schema::connection($this->conn)->hasTable('loan_files')) {
            return null;
        }

        if (preg_match('/^data:([^;]+);base64,/', $dataUri, $match)) {
            $mimeType = $match[1];
            $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
        } else {
            $mimeType = 'application/octet-stream';
        }

        $binary = base64_decode($dataUri, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = $this->extensionFromMimeType($mimeType);
        $path = 'payment-documents/'.$paymentId.'/'.$namePrefix.'-'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return (int) DB::connection($this->conn)->table('loan_files')->insertGetId($this->safeColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\LoanPayment::class,
            'fileable_id' => $paymentId,
            'category' => 'payment_doc',
            'disk' => 'public',
            'path' => $path,
            'original_name' => $namePrefix.'.'.$extension,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($binary),
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function extensionFromMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv', 'application/csv' => 'csv',
            default => 'bin',
        };
    }

    protected function ensurePaymentTypeColumn(): void
    {
        if (! Schema::connection($this->conn)->hasTable('loan_payments')
            || Schema::connection($this->conn)->hasColumn('loan_payments', 'payment_type')) {
            return;
        }

        Schema::connection($this->conn)->table('loan_payments', function ($table) {
            $table->string('payment_type', 20)->default('monthly')->after('loan_id');
        });
    }
}
