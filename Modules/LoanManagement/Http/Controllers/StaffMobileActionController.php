<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffMobileActionController extends Controller
{
    use ApiResponseTrait;

    protected string $conn = 'mysql_loan';

    public function receivePayment(Request $request)
    {
        $data = $request->validate([
            'loan_id' => 'required|integer|min:1',
            'customer_id' => 'required|integer|min:1',
            'currency' => 'required|in:USD,KHR',
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'nullable|date',
            'note' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.method' => 'required|string|max:30',
            'details.*.amount' => 'required|numeric|min:0.01',
            'details.*.currency' => 'nullable|in:USD,KHR',
            'details.*.exchange_rate' => 'nullable|numeric|min:0.0001',
            'details.*.transaction_no' => 'nullable|string|max:255',
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
                'customer_id' => $data['customer_id'],
                'amount' => $amount,
                'paid_at' => $payAt,
                'channel' => 'mobile',
                'status' => 'confirmed',
                'note' => $data['note'] ?? null,
                'payment_ref_no' => 'PMT-'.strtoupper(Str::random(10)),
                'received_by' => auth()->id(),
                'received_by_name_snapshot' => trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? ''))),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $paymentId = DB::connection($this->conn)->table('loan_payments')->insertGetId($this->safeColumns('loan_payments', $paymentPayload));

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
                    ->orderBy('due_date')
                    ->orderBy('id')
                    ->get();

                foreach ($schedules as $s) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $due = (float) ($s->amount_balance ?? $s->amount_due ?? 0);
                    if ($due <= 0) {
                        continue;
                    }
                    $applied = min($remaining, $due);
                    $newPaid = (float) ($s->amount_paid ?? 0) + $applied;
                    $newBalance = max(0, $due - $applied);
                    $status = $newBalance <= 0 ? 'paid' : 'partial';

                    DB::connection($this->conn)->table('loan_payment_schedules')->where('id', $s->id)->update($this->safeColumns('loan_payment_schedules', [
                        'amount_paid' => $newPaid,
                        'amount_balance' => $newBalance,
                        'status' => $status,
                        'paid_at' => $newBalance <= 0 ? $payAt : null,
                        'updated_at' => now(),
                    ]));
                    $remaining -= $applied;
                }
            }

            $loanFresh = DB::connection($this->conn)->table('loans')->where('id', $data['loan_id'])->first();
            $newPaidAmount = (float) ($loanFresh->paid_amount ?? 0) + $amount;
            $totalAmount = (float) ($loanFresh->total_amount ?? 0);
            $newBalanceAmount = max(0, $totalAmount - $newPaidAmount);
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
            ];
        });

        return $this->ok('Payment received successfully', $result);
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
}

