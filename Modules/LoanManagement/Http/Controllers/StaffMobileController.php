<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffMobileController extends Controller
{
    use ApiResponseTrait;

    protected string $conn = 'mysql_loan';

    public function dashboard()
    {
        $today = now()->toDateString();
        $activeLoans = 0;
        $todayCollection = 0.0;
        $lateCustomers = 0;
        $todayNewCustomerSummary = $this->todayNewCustomerSummary($today);

        if (Schema::connection($this->conn)->hasTable('loans')) {
            $activeLoans = (int) DB::connection($this->conn)->table('loans')->whereIn('status', ['active', 'approved', 'late'])->count();
        }

        if (Schema::connection($this->conn)->hasTable('loan_payments')) {
            $q = DB::connection($this->conn)->table('loan_payments');
            if (Schema::connection($this->conn)->hasColumn('loan_payments', 'paid_at')) {
                $q->whereDate('paid_at', $today);
            } elseif (Schema::connection($this->conn)->hasColumn('loan_payments', 'paid_date')) {
                $q->whereDate('paid_date', $today);
            }
            $todayCollection = (float) $q->sum(Schema::connection($this->conn)->hasColumn('loan_payments', 'amount') ? 'amount' : 'total_paid_base');
        }

        if (Schema::connection($this->conn)->hasTable('loan_payment_schedules') && Schema::connection($this->conn)->hasTable('loans')) {
            $lateCustomers = (int) DB::connection($this->conn)->table('loan_payment_schedules as s')
                ->join('loans as l', 'l.id', '=', 's.loan_id')
                ->whereDate('s.due_date', '<', $today)
                ->whereIn('s.status', ['pending', 'unpaid', 'partial', 'late'])
                ->distinct('l.customer_id')
                ->count('l.customer_id');
        }

        return $this->ok('Dashboard loaded', [
            'active_customers' => $this->activeCustomersCount(),
            'today_new_customers' => $todayNewCustomerSummary['count'],
            'today_new_customer_payments' => $this->money($todayNewCustomerSummary['payments']),
            'today_new_customer_date' => $todayNewCustomerSummary['date'],
            'active_loans' => $activeLoans,
            'today_collection' => $this->money($todayCollection),
            'monthly_collection' => $this->monthlyCollection(),
            'unread_chats' => $this->unreadChatsCount(),
            'late_customers' => $lateCustomers,
        ]);
    }

    public function customers()
    {
        if (! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return $this->ok('Customers loaded', []);
        }

        $rows = DB::connection($this->conn)->table('loan_customers')
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn ($row) => $this->customerMobilePayload($row))
            ->values();

        return $this->ok('Customers loaded', $rows);
    }

    public function customerShow(int $id)
    {
        if (! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return $this->fail('Customer not found', 404, (object) []);
        }

        $row = DB::connection($this->conn)->table('loan_customers')->where('id', $id)->first();
        if (! $row) {
            return $this->fail('Customer not found', 404, (object) []);
        }

        return $this->ok('Customer loaded', $this->customerMobilePayload($row));
    }

    public function customerUpdate(Request $request, int $id)
    {
        if (! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return $this->fail('Customer not found', 404, (object) []);
        }

        $row = DB::connection($this->conn)->table('loan_customers')->where('id', $id)->first();
        if (! $row) {
            return $this->fail('Customer not found', 404, (object) []);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'khmer_name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:191'],
            'id_card_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $payload = $this->safeColumns('loan_customers', [
            'name' => trim((string) ($data['name'] ?? '')) ?: null,
            'khmer_name' => trim((string) ($data['khmer_name'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'login_phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'id_card_number' => trim((string) ($data['id_card_number'] ?? '')) ?: null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'status' => trim((string) ($data['status'] ?? '')) ?: null,
            'updated_at' => now(),
        ]);

        $payload = array_filter($payload, fn ($value) => $value !== null);
        if (! empty($payload)) {
            DB::connection($this->conn)->table('loan_customers')->where('id', $id)->update($payload);
        }

        $fresh = DB::connection($this->conn)->table('loan_customers')->where('id', $id)->first();

        return $this->ok('Customer updated', $this->customerMobilePayload($fresh));
    }

    public function customerVerify(int $id)
    {
        if (! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return $this->fail('Customer not found', 404, (object) []);
        }

        $row = DB::connection($this->conn)->table('loan_customers')->where('id', $id)->first();
        if (! $row) {
            return $this->fail('Customer not found', 404, (object) []);
        }

        $payload = $this->safeColumns('loan_customers', [
            'verified' => 1,
            'is_verified' => 1,
            'information_verified' => 1,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'updated_at' => now(),
        ]);

        if (! empty($payload)) {
            DB::connection($this->conn)->table('loan_customers')->where('id', $id)->update($payload);
        }

        $fresh = DB::connection($this->conn)->table('loan_customers')->where('id', $id)->first();

        return $this->ok('Customer verified', $this->customerMobilePayload($fresh));
    }

    public function lateCustomers()
    {
        if (! Schema::connection($this->conn)->hasTable('loan_payment_schedules') || ! Schema::connection($this->conn)->hasTable('loans')) {
            return $this->ok('Late customers loaded', []);
        }

        $balanceExpression = $this->scheduleBalanceExpression();

        $rows = DB::connection($this->conn)->table('loan_payment_schedules as s')
            ->join('loans as l', 'l.id', '=', 's.loan_id')
            ->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id')
            ->selectRaw("l.customer_id as id, l.customer_id, MAX(COALESCE(NULLIF(c.name, ''), NULLIF(c.khmer_name, ''), NULLIF(l.customer_name_snapshot, ''), c.customer_code)) as customer_name, MAX(COALESCE(NULLIF(c.phone, ''), NULLIF(c.login_phone, ''), NULLIF(l.customer_phone_snapshot, ''))) as phone, SUM({$balanceExpression}) as late_amount")
            ->whereDate('s.due_date', '<', now()->toDateString())
            ->whereIn('s.status', ['pending', 'unpaid', 'partial', 'late'])
            ->groupBy('l.customer_id')
            ->orderByDesc('late_amount')
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $r->late_amount = $this->money($r->late_amount);
                $r->status = 'Late';
                return $r;
            })->values();

        return $this->ok('Late customers loaded', $rows);
    }

    protected function scheduleBalanceExpression(): string
    {
        $columns = Schema::connection($this->conn)->getColumnListing('loan_payment_schedules');
        $available = array_values(array_intersect(
            ['balance_amount', 'amount_balance', 'balance', 'amount_due', 'schedule_amount', 'amount'],
            $columns
        ));

        if (empty($available)) {
            return '0';
        }

        return 'COALESCE('.implode(', ', array_map(fn ($column) => 's.'.$column, $available)).', 0)';
    }

    protected function customerMobilePayload(?object $row): array
    {
        $name = trim((string) ($row->name ?? ''));
        $khmerName = trim((string) ($row->khmer_name ?? ''));
        $customerCode = trim((string) ($row->customer_code ?? ''));
        $phone = trim((string) ($row->phone ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($row->login_phone ?? ''));
        }

        return [
            'id' => (int) ($row->id ?? $row->customer_id ?? 0),
            'customer_id' => (int) ($row->id ?? $row->customer_id ?? 0),
            'customer_code' => $customerCode,
            'name' => $name !== '' ? $name : ($khmerName !== '' ? $khmerName : $customerCode),
            'khmer_name' => $khmerName,
            'phone' => $phone,
            'status' => (string) ($row->status ?? ''),
            'late_amount' => $this->money($row->late_amount ?? 0),
            'id_card_number' => (string) ($row->id_card_number ?? ''),
            'address' => (string) ($row->address ?? ''),
            'province' => (string) ($row->province ?? ''),
            'district' => (string) ($row->district ?? ''),
            'commune' => (string) ($row->commune ?? ''),
            'village' => (string) ($row->village ?? ''),
            'verified' => (bool) (($row->verified ?? $row->is_verified ?? $row->information_verified ?? false)),
        ];
    }

    protected function activeCustomersCount(): int
    {
        if (! Schema::connection($this->conn)->hasTable('loans')) {
            return 0;
        }

        $query = DB::connection($this->conn)->table('loans')
            ->whereNotIn('status', ['closed', 'paid', 'completed', 'cancelled'])
            ->whereNotNull('customer_id');

        $columns = Schema::connection($this->conn)->getColumnListing('loans');
        $balanceColumn = collect(['balance_amount', 'amount_balance', 'remaining_balance'])
            ->first(fn ($column) => in_array($column, $columns, true));
        if ($balanceColumn) {
            $query->where($balanceColumn, '>', 0);
        }

        return (int) $query->distinct('customer_id')->count('customer_id');
    }

    protected function todayNewCustomerSummary(string $today): array
    {
        if (! Schema::connection($this->conn)->hasTable('loans')) {
            return ['count' => 0, 'payments' => 0.0, 'date' => $today];
        }

        $loanColumns = Schema::connection($this->conn)->getColumnListing('loans');
        $dateColumns = array_values(array_intersect(
            ['loan_date', 'source_created_at', 'sale_date', 'created_at'],
            $loanColumns
        ));

        if (empty($dateColumns)) {
            return ['count' => 0, 'payments' => 0.0, 'date' => $today];
        }

        $selectColumns = ['id'];
        foreach (['customer_id', 'down_payment', 'paid_amount', 'initial_payment_amount'] as $column) {
            if (in_array($column, $loanColumns, true)) {
                $selectColumns[] = $column;
            }
        }

        $todayLoanQuery = DB::connection($this->conn)->table('loans')
            ->where(function ($query) use ($dateColumns, $today) {
                foreach ($dateColumns as $index => $column) {
                    $method = $index === 0 ? 'whereDate' : 'orWhereDate';
                    $query->{$method}($column, $today);
                }
            });

        if (! in_array('customer_id', $selectColumns, true)) {
            $selectColumns[] = 'customer_id';
        }

        $queryColumns = array_values(array_unique(
            array_intersect($selectColumns, $loanColumns)
        ));

        $todayLoans = $todayLoanQuery->get($queryColumns);
        $effectiveDate = $today;

        if ($todayLoans->isEmpty()) {
            $latestDate = $this->latestLoanBusinessDate($dateColumns);
            if ($latestDate && $latestDate !== $today) {
                $effectiveDate = $latestDate;
                $todayLoans = DB::connection($this->conn)->table('loans')
                    ->where(function ($query) use ($dateColumns, $effectiveDate) {
                        foreach ($dateColumns as $index => $column) {
                            $method = $index === 0 ? 'whereDate' : 'orWhereDate';
                            $query->{$method}($column, $effectiveDate);
                        }
                    })
                    ->get($queryColumns);
            }
        }

        if ($todayLoans->isEmpty()) {
            return ['count' => 0, 'payments' => 0.0, 'date' => $effectiveDate];
        }

        $loanIds = $todayLoans->pluck('id')->filter()->values();
        $customerIds = $todayLoans
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values();

        if ($loanIds->isEmpty()) {
            return ['count' => 0, 'payments' => 0.0, 'date' => $effectiveDate];
        }

        $payments = 0.0;
        if (Schema::connection($this->conn)->hasTable('loan_payments')) {
            $paymentColumns = Schema::connection($this->conn)->getColumnListing('loan_payments');
            $amountColumn = collect(['amount', 'total_paid_base', 'paid_amount', 'total_amount'])
                ->first(fn ($column) => in_array($column, $paymentColumns, true));

            if ($amountColumn && in_array('loan_id', $paymentColumns, true)) {
                $paymentQuery = DB::connection($this->conn)->table('loan_payments as p')
                    ->whereIn('p.loan_id', $loanIds->all());
                $payments = (float) $paymentQuery->sum('p.'.$amountColumn);
            }
        }

        if ($payments <= 0) {
            $loanPaymentColumns = array_values(array_intersect(
                ['down_payment', 'paid_amount', 'initial_payment_amount'],
                $loanColumns
            ));

            foreach ($todayLoans as $loan) {
                foreach ($loanPaymentColumns as $column) {
                    $payments += (float) ($loan->{$column} ?? 0);
                }
            }
        }

        return [
            'count' => $customerIds->isNotEmpty() ? $customerIds->count() : $loanIds->count(),
            'payments' => $payments,
            'date' => $effectiveDate,
        ];
    }

    protected function latestLoanBusinessDate(array $dateColumns): ?string
    {
        $latest = null;

        foreach ($dateColumns as $column) {
            $value = DB::connection($this->conn)->table('loans')
                ->whereNotNull($column)
                ->max($column);
            if (! $value) {
                continue;
            }

            $date = substr((string) $value, 0, 10);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            if ($latest === null || $date > $latest) {
                $latest = $date;
            }
        }

        return $latest;
    }

    protected function monthlyCollection(): float
    {
        if (! Schema::connection($this->conn)->hasTable('loan_payments')) {
            return 0.0;
        }

        $q = DB::connection($this->conn)->table('loan_payments');
        if (Schema::connection($this->conn)->hasColumn('loan_payments', 'paid_at')) {
            $q->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year);
        } elseif (Schema::connection($this->conn)->hasColumn('loan_payments', 'paid_date')) {
            $q->whereMonth('paid_date', now()->month)->whereYear('paid_date', now()->year);
        }

        return $this->money($q->sum(Schema::connection($this->conn)->hasColumn('loan_payments', 'amount') ? 'amount' : 'total_paid_base'));
    }

    protected function unreadChatsCount(): int
    {
        if (! Schema::connection($this->conn)->hasTable('loan_chat_messages')) {
            return 0;
        }

        $q = DB::connection($this->conn)->table('loan_chat_messages');
        if (Schema::connection($this->conn)->hasColumn('loan_chat_messages', 'read_at')) {
            $q->whereNull('read_at');
        }
        if (Schema::connection($this->conn)->hasColumn('loan_chat_messages', 'sender_type')) {
            $q->where('sender_type', 'customer');
        }

        return (int) $q->count();
    }

    protected function safeColumns(string $table, array $payload): array
    {
        if (! Schema::connection($this->conn)->hasTable($table)) {
            return [];
        }

        $columns = Schema::connection($this->conn)->getColumnListing($table);

        return array_intersect_key($payload, array_flip($columns));
    }
}
