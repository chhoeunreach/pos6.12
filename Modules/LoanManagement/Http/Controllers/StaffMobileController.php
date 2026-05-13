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
            'active_loans' => $activeLoans,
            'today_collection' => $this->money($todayCollection),
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
            ->select('id', 'customer_code', 'name', 'phone', 'login_phone', 'status')
            ->orderByDesc('id')->limit(200)->get();

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

        return $this->ok('Customer loaded', $row);
    }

    public function lateCustomers()
    {
        if (! Schema::connection($this->conn)->hasTable('loan_payment_schedules') || ! Schema::connection($this->conn)->hasTable('loans')) {
            return $this->ok('Late customers loaded', []);
        }

        $rows = DB::connection($this->conn)->table('loan_payment_schedules as s')
            ->join('loans as l', 'l.id', '=', 's.loan_id')
            ->selectRaw('l.customer_id, MAX(l.customer_name_snapshot) as customer_name, MAX(l.customer_phone_snapshot) as phone, SUM(COALESCE(s.amount_balance, s.amount_due, 0)) as late_amount')
            ->whereDate('s.due_date', '<', now()->toDateString())
            ->whereIn('s.status', ['pending', 'unpaid', 'partial', 'late'])
            ->groupBy('l.customer_id')
            ->orderByDesc('late_amount')
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $r->late_amount = $this->money($r->late_amount);
                return $r;
            })->values();

        return $this->ok('Late customers loaded', $rows);
    }
}

