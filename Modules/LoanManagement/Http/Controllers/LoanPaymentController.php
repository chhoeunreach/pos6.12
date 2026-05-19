<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanPaymentController extends Controller
{
    protected string $connection = 'mysql_loan';

    public function index(Request $request)
    {
        abort_if(! Schema::connection($this->connection)->hasTable('loan_payments'), 404);

        $filters = $request->only([
            'search',
            'loan_number',
            'customer',
            'method',
            'status',
            'date_from',
            'date_to',
            'location_id',
        ]);

        $query = $this->basePaymentQuery();
        $this->applyFilters($query, $filters);

        $summaryQuery = clone $query;
        $amountExpr = $this->paymentAmountExpression();
        $summary = [
            'count' => (int) (clone $summaryQuery)->count(),
            'amount' => (float) (clone $summaryQuery)->sum(DB::raw($amountExpr)),
        ];

        $payments = $query
            ->orderByDesc('p.'.$this->paymentDateColumn())
            ->orderByDesc('p.id')
            ->paginate(25)
            ->appends($request->query());

        return view('loanmanagement::payments.index', [
            'payments' => $payments,
            'summary' => $summary,
            'filters' => $filters,
            'methods' => $this->paymentMethodOptions(),
            'statuses' => $this->distinctOptions('loan_payments', 'status'),
            'locations' => $this->locationOptions(),
            'dateColumn' => $this->paymentDateColumn(),
            'amountColumn' => $this->paymentAmountColumn(),
        ]);
    }

    public function edit(int $payment)
    {
        $row = $this->paymentRow($payment);
        abort_if(! $row, 404);

        $loan = DB::connection($this->connection)->table('loans')->where('id', $row->loan_id)->first();
        $schedules = Schema::connection($this->connection)->hasTable('loan_payment_schedules')
            ? DB::connection($this->connection)->table('loan_payment_schedules')->where('loan_id', $row->loan_id)->orderBy('id')->get()
            : collect();

        return view('loanmanagement::payments.edit', [
            'payment' => $row,
            'loan' => $loan,
            'schedules' => $schedules,
            'methods' => $this->paymentMethodOptions($loan),
        ]);
    }

    public function update(Request $request, int $payment)
    {
        $row = $this->paymentRow($payment);
        abort_if(! $row, 404);

        $payload = $request->validate([
            'paid_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:100',
            'schedule_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:1000',
        ]);

        $loan = DB::connection($this->connection)->table('loans')->where('id', $row->loan_id)->first();
        $paymentTypes = $this->paymentMethodOptions($loan);
        $method = trim((string) ($payload['method'] ?? ''));
        $methodName = $this->paymentMethodName($method, $paymentTypes);
        $newAmount = round((float) $payload['amount'], 2);
        $oldAmount = (float) ($row->total_paid_base ?? $row->total_paid ?? $row->amount ?? 0);
        $newScheduleId = ! empty($payload['schedule_id']) ? (int) $payload['schedule_id'] : null;
        $oldScheduleId = ! empty($row->schedule_id) ? (int) $row->schedule_id : null;
        $paidDate = $payload['paid_date'];
        $paidAt = $paidDate.' '.now()->format('H:i:s');

        DB::connection($this->connection)->transaction(function () use ($payment, $row, $payload, $method, $methodName, $newAmount, $oldAmount, $newScheduleId, $oldScheduleId, $paidDate, $paidAt) {
            DB::connection($this->connection)->table('loan_payments')->where('id', $payment)->update($this->safeColumns('loan_payments', [
                'schedule_id' => $newScheduleId,
                'payment_method_snapshot' => $methodName,
                'channel' => $methodName,
                'amount' => $newAmount,
                'total_paid' => $newAmount,
                'total_paid_base' => $newAmount,
                'reference_number' => trim((string) ($payload['reference_number'] ?? '')) ?: null,
                'paid_date' => $paidDate,
                'paid_at' => $paidAt,
                'status' => trim((string) ($payload['status'] ?? 'confirmed')) ?: 'confirmed',
                'note' => trim((string) ($payload['note'] ?? '')) ?: null,
                'updated_at' => now(),
            ]));

            if (Schema::connection($this->connection)->hasTable('loan_payment_details')) {
                $detail = DB::connection($this->connection)->table('loan_payment_details')->where('payment_id', $payment)->orderBy('id')->first();
                $detailPayload = $this->safeColumns('loan_payment_details', [
                    'payment_method_snapshot' => $methodName,
                    'method' => $method !== '' ? $method : $methodName,
                    'amount' => $newAmount,
                    'amount_base' => $newAmount,
                    'reference_number' => trim((string) ($payload['reference_number'] ?? '')) ?: null,
                    'transaction_no' => trim((string) ($payload['reference_number'] ?? '')) ?: null,
                    'note' => trim((string) ($payload['note'] ?? '')) ?: null,
                    'updated_at' => now(),
                ]);

                if ($detail) {
                    DB::connection($this->connection)->table('loan_payment_details')->where('id', $detail->id)->update($detailPayload);
                } else {
                    $detailPayload = array_merge($detailPayload, $this->safeColumns('loan_payment_details', [
                        'payment_id' => $payment,
                        'created_at' => now(),
                    ]));
                    DB::connection($this->connection)->table('loan_payment_details')->insert($detailPayload);
                }
            }

            if ($oldScheduleId && $oldScheduleId !== $newScheduleId) {
                $this->adjustSchedulePayment($oldScheduleId, -$oldAmount, $paidAt);
                if ($newScheduleId) {
                    $this->adjustSchedulePayment($newScheduleId, $newAmount, $paidAt);
                }
            } elseif ($newScheduleId) {
                $this->adjustSchedulePayment($newScheduleId, $newAmount - $oldAmount, $paidAt);
            }

            $this->refreshLoanTotals((int) $row->loan_id);
        });

        return redirect()
            ->route('loan-management.payments.index')
            ->with('status', ['success' => 1, 'msg' => 'Payment updated successfully.']);
    }

    public function destroy(int $payment)
    {
        $row = $this->paymentRow($payment);
        abort_if(! $row, 404);

        DB::connection($this->connection)->transaction(function () use ($payment, $row) {
            $amount = (float) ($row->total_paid_base ?? $row->total_paid ?? $row->amount ?? 0);
            if (! empty($row->schedule_id)) {
                $this->adjustSchedulePayment((int) $row->schedule_id, -$amount, now()->toDateTimeString());
            }

            if (Schema::connection($this->connection)->hasTable('loan_payment_details')) {
                DB::connection($this->connection)->table('loan_payment_details')->where('payment_id', $payment)->delete();
            }

            DB::connection($this->connection)->table('loan_payments')->where('id', $payment)->delete();
            $this->refreshLoanTotals((int) $row->loan_id);
        });

        return redirect()
            ->route('loan-management.payments.index')
            ->with('status', ['success' => 1, 'msg' => 'Payment deleted successfully.']);
    }

    protected function basePaymentQuery()
    {
        $dateColumn = $this->paymentDateColumn();
        $amountColumn = $this->paymentAmountColumn();
        $methodColumn = $this->paymentMethodColumn();
        $receiptExpression = $this->receiptExpression();
        $loanNumberExpression = $this->loanValueExpression('loan_number', $this->hasColumn('loan_payments', 'loan_number_snapshot') ? 'p.loan_number_snapshot' : 'NULL');
        $customerNameExpression = $this->loanValueExpression('customer_name_snapshot', $this->hasColumn('loan_payments', 'customer_name_snapshot') ? 'p.customer_name_snapshot' : 'NULL');
        $customerPhoneExpression = $this->loanValueExpression('customer_phone_snapshot', "''");
        $locationNameExpression = $this->loanValueExpression('location_name_snapshot', 'NULL');
        $businessLocationExpression = $this->loanValueExpression('business_location_id', 'NULL');
        $mainLocationExpression = $this->loanValueExpression('main_location_id', 'NULL');
        $customerIdExpression = $this->hasColumn('loan_payments', 'customer_id')
            ? 'p.customer_id'
            : ($this->hasColumn('loans', 'customer_id') ? 'l.customer_id' : 'NULL');
        $scheduleIdExpression = $this->hasColumn('loan_payments', 'schedule_id') ? 'p.schedule_id' : 'NULL';

        $query = DB::connection($this->connection)->table('loan_payments as p')
            ->leftJoin('loans as l', 'l.id', '=', 'p.loan_id')
            ->selectRaw("
                p.id,
                p.loan_id,
                {$customerIdExpression} as customer_id,
                {$scheduleIdExpression} as schedule_id,
                {$receiptExpression} as receipt_number,
                p.{$dateColumn} as paid_date,
                p.{$amountColumn} as amount,
                {$methodColumn} as payment_method,
                ".($this->hasColumn('loan_payments', 'status') ? 'p.status' : "'confirmed'")." as status,
                ".($this->hasColumn('loan_payments', 'reference_number') ? 'p.reference_number' : 'NULL')." as reference_number,
                ".($this->hasColumn('loan_payments', 'note') ? 'p.note' : 'NULL')." as note,
                ".($this->hasColumn('loan_payments', 'received_by_name_snapshot') ? 'p.received_by_name_snapshot' : ($this->hasColumn('loan_payments', 'collected_by_name_snapshot') ? 'p.collected_by_name_snapshot' : 'NULL'))." as received_by,
                {$loanNumberExpression} as loan_number,
                {$customerNameExpression} as customer_name,
                {$customerPhoneExpression} as customer_phone,
                {$locationNameExpression} as location_name_snapshot,
                {$businessLocationExpression} as business_location_id,
                {$mainLocationExpression} as main_location_id
            ");

        return $query;
    }

    protected function applyFilters($query, array $filters): void
    {
        $dateColumn = 'p.'.$this->paymentDateColumn();

        if (! empty($filters['date_from'])) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }
        if (! empty($filters['loan_number'])) {
            $query->where(function ($q) use ($filters) {
                $hasCondition = false;
                if ($this->hasColumn('loans', 'loan_number')) {
                    $q->where('l.loan_number', 'like', '%'.$filters['loan_number'].'%');
                    $hasCondition = true;
                }
                if ($this->hasColumn('loan_payments', 'loan_number_snapshot')) {
                    $hasCondition
                        ? $q->orWhere('p.loan_number_snapshot', 'like', '%'.$filters['loan_number'].'%')
                        : $q->where('p.loan_number_snapshot', 'like', '%'.$filters['loan_number'].'%');
                }
            });
        }
        if (! empty($filters['customer'])) {
            $query->where(function ($q) use ($filters) {
                $hasCondition = false;
                if ($this->hasColumn('loans', 'customer_name_snapshot')) {
                    $q->where('l.customer_name_snapshot', 'like', '%'.$filters['customer'].'%');
                    $hasCondition = true;
                }
                if ($this->hasColumn('loans', 'customer_phone_snapshot')) {
                    $hasCondition
                        ? $q->orWhere('l.customer_phone_snapshot', 'like', '%'.$filters['customer'].'%')
                        : $q->where('l.customer_phone_snapshot', 'like', '%'.$filters['customer'].'%');
                    $hasCondition = true;
                }
                if ($this->hasColumn('loan_payments', 'customer_name_snapshot')) {
                    $hasCondition
                        ? $q->orWhere('p.customer_name_snapshot', 'like', '%'.$filters['customer'].'%')
                        : $q->where('p.customer_name_snapshot', 'like', '%'.$filters['customer'].'%');
                }
            });
        }
        if (! empty($filters['method'])) {
            $methodColumn = $this->paymentMethodColumn();
            $query->whereRaw($methodColumn.' LIKE ?', ['%'.$filters['method'].'%']);
        }
        if (! empty($filters['status']) && $this->hasColumn('loan_payments', 'status')) {
            $query->where('p.status', $filters['status']);
        }
        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            $query->where(function ($q) use ($locationId) {
                $hasCondition = false;
                if ($this->hasColumn('loans', 'business_location_id')) {
                    $q->where('l.business_location_id', $locationId);
                    $hasCondition = true;
                }
                if ($this->hasColumn('loans', 'main_location_id')) {
                    $hasCondition
                        ? $q->orWhere('l.main_location_id', $locationId)
                        : $q->where('l.main_location_id', $locationId);
                }
            });
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $hasCondition = false;
                foreach ([
                    ['loans', 'loan_number', 'l.loan_number'],
                    ['loans', 'customer_name_snapshot', 'l.customer_name_snapshot'],
                    ['loans', 'customer_phone_snapshot', 'l.customer_phone_snapshot'],
                    ['loan_payments', 'receipt_number', 'p.receipt_number'],
                    ['loan_payments', 'payment_ref_no', 'p.payment_ref_no'],
                    ['loan_payments', 'reference_number', 'p.reference_number'],
                ] as [$table, $column, $qualified]) {
                    if (! $this->hasColumn($table, $column)) {
                        continue;
                    }

                    $hasCondition ? $q->orWhere($qualified, 'like', $search) : $q->where($qualified, 'like', $search);
                    $hasCondition = true;
                }
            });
        }
    }

    protected function paymentRow(int $payment)
    {
        return DB::connection($this->connection)->table('loan_payments')->where('id', $payment)->first();
    }

    protected function adjustSchedulePayment(int $scheduleId, float $diff, string $paidAt): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return;
        }

        $schedule = DB::connection($this->connection)->table('loan_payment_schedules')->where('id', $scheduleId)->first();
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

        DB::connection($this->connection)->table('loan_payment_schedules')->where('id', $scheduleId)->update($this->safeColumns('loan_payment_schedules', [
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
        $loan = DB::connection($this->connection)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            return;
        }

        $amountColumn = $this->paymentAmountColumn();
        $paid = (float) DB::connection($this->connection)->table('loan_payments')->where('loan_id', $loanId)->sum($amountColumn);
        $balance = null;
        if (Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            if ($this->hasColumn('loan_payment_schedules', 'balance_amount')) {
                $balance = (float) DB::connection($this->connection)->table('loan_payment_schedules')->where('loan_id', $loanId)->sum('balance_amount');
            } elseif ($this->hasColumn('loan_payment_schedules', 'amount_balance')) {
                $balance = (float) DB::connection($this->connection)->table('loan_payment_schedules')->where('loan_id', $loanId)->sum('amount_balance');
            }
        }

        if ($balance === null) {
            $principal = (float) ($loan->principal_amount ?? $loan->total_payable_amount ?? 0);
            $balance = max(0, $principal - $paid);
        }

        DB::connection($this->connection)->table('loans')->where('id', $loanId)->update($this->safeColumns('loans', [
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'last_payment_amount' => $paid > 0 ? $this->lastPaymentAmount($loanId) : null,
            'last_payment_date' => $paid > 0 ? $this->lastPaymentDate($loanId) : null,
            'status' => $balance <= 0 ? 'closed' : ($loan->status === 'closed' ? 'active' : ($loan->status ?? 'active')),
            'updated_at' => now(),
        ]));
    }

    protected function lastPaymentAmount(int $loanId): ?float
    {
        $row = DB::connection($this->connection)->table('loan_payments')->where('loan_id', $loanId)->orderByDesc($this->paymentDateColumn())->orderByDesc('id')->first();
        return $row ? (float) ($row->{$this->paymentAmountColumn()} ?? 0) : null;
    }

    protected function lastPaymentDate(int $loanId): ?string
    {
        $row = DB::connection($this->connection)->table('loan_payments')->where('loan_id', $loanId)->orderByDesc($this->paymentDateColumn())->orderByDesc('id')->first();
        return $row ? (string) ($row->{$this->paymentDateColumn()} ?? null) : null;
    }

    protected function paymentMethodOptions($loan = null): array
    {
        try {
            return app(TransactionUtil::class)->payment_types($loan->main_location_id ?? null, true, (int) (session('user.business_id') ?? 0));
        } catch (\Throwable $e) {
            return ['cash' => 'Cash', 'aba' => 'ធនាគារអេប៊ីអេ (ABA)', 'wing' => 'វីងវេលុយ (Wing)'];
        }
    }

    protected function paymentMethodName(string $method, array $paymentTypes): string
    {
        $method = trim($method);
        if ($method === '') {
            $method = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? 'cash');
        }

        $normalized = strtolower(str_replace([' ', '-', '_'], '', $method));
        $known = [
            'aba' => 'ធនាគារអេប៊ីអេ (ABA)',
            'ababank' => 'ធនាគារអេប៊ីអេ (ABA)',
            'abapay' => 'ធនាគារអេប៊ីអេ (ABA)',
            'wing' => 'វីងវេលុយ (Wing)',
            'wingmoney' => 'វីងវេលុយ (Wing)',
            'cash' => 'Cash',
        ];

        return $known[$normalized] ?? (string) ($paymentTypes[$method] ?? ucfirst(str_replace('_', ' ', $method)));
    }

    protected function locationOptions()
    {
        if (! Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            return collect();
        }

        return DB::connection($this->connection)->table('loan_business_locations')->orderBy('name')->pluck('name', 'id');
    }

    protected function distinctOptions(string $table, string $column)
    {
        if (! $this->hasColumn($table, $column)) {
            return collect();
        }

        return DB::connection($this->connection)->table($table)->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column, $column);
    }

    protected function paymentDateColumn(): string
    {
        return $this->hasColumn('loan_payments', 'paid_date') ? 'paid_date' : 'paid_at';
    }

    protected function paymentAmountColumn(): string
    {
        if ($this->hasColumn('loan_payments', 'total_paid_base')) return 'total_paid_base';
        if ($this->hasColumn('loan_payments', 'total_paid')) return 'total_paid';
        return 'amount';
    }

    protected function paymentAmountExpression(): string
    {
        return 'p.'.$this->paymentAmountColumn();
    }

    protected function paymentMethodColumn(): string
    {
        if ($this->hasColumn('loan_payments', 'payment_method_snapshot')) return 'p.payment_method_snapshot';
        if ($this->hasColumn('loan_payments', 'channel')) return 'p.channel';
        return "'Payment'";
    }

    protected function receiptExpression(): string
    {
        if ($this->hasColumn('loan_payments', 'receipt_number') && $this->hasColumn('loan_payments', 'payment_ref_no')) {
            return 'COALESCE(p.receipt_number, p.payment_ref_no)';
        }
        if ($this->hasColumn('loan_payments', 'receipt_number')) return 'p.receipt_number';
        if ($this->hasColumn('loan_payments', 'payment_ref_no')) return 'p.payment_ref_no';
        return 'CAST(p.id AS CHAR)';
    }

    protected function loanValueExpression(string $loanColumn, string $fallbackExpression): string
    {
        return $this->hasColumn('loans', $loanColumn)
            ? 'COALESCE(l.'.$loanColumn.', '.$fallbackExpression.')'
            : $fallbackExpression;
    }

    protected function safeColumns(string $table, array $payload): array
    {
        return array_intersect_key($payload, array_flip(Schema::connection($this->connection)->getColumnListing($table)));
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return Schema::connection($this->connection)->hasTable($table)
            && Schema::connection($this->connection)->hasColumn($table, $column);
    }
}
