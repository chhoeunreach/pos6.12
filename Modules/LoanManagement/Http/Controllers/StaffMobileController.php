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
        $pendingVisits = 0;

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

        if (Schema::connection($this->conn)->hasTable('loan_collection_visits')) {
            $visitQ = DB::connection($this->conn)->table('loan_collection_visits');
            if (Schema::connection($this->conn)->hasColumn('loan_collection_visits', 'result')) {
                $visitQ->whereIn('result', ['pending', 'follow_up', 'rescheduled']);
            } elseif (Schema::connection($this->conn)->hasColumn('loan_collection_visits', 'status')) {
                $visitQ->whereIn('status', ['pending', 'follow_up', 'rescheduled']);
            } else {
                $visitQ->whereRaw('1=0');
            }
            $pendingVisits = (int) $visitQ->count();
        }

        return $this->ok('Dashboard loaded', [
            'assigned_customers' => $this->assignedCustomersCount(),
            'active_loans' => $activeLoans,
            'today_collection' => $this->money($todayCollection),
            'monthly_collection' => $this->monthlyCollection(),
            'unread_chats' => $this->unreadChatsCount(),
            'late_customers' => $lateCustomers,
            'pending_visits' => $pendingVisits,
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

    protected function assignedCustomersCount(): int
    {
        if (! Schema::connection($this->conn)->hasTable('loan_customers')) {
            return 0;
        }

        return (int) DB::connection($this->conn)->table('loan_customers')->count();
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
