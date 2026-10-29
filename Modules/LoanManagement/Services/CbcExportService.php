<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CbcExportService
{
    private array $columns = [];

    private function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::connection('mysql_loan')->hasTable($table)
            ? Schema::connection('mysql_loan')->getColumnListing($table) : [];
    }

    private function first(string $table, array $names): ?string
    {
        foreach ($names as $name) {
            if (in_array($name, $this->columns($table), true)) { return $name; }
        }
        return null;
    }

    private function value(string $table, string $alias, array $names, string $fallback = 'NULL'): string
    {
        $names = array_values(array_intersect($names, $this->columns($table)));
        return $names ? 'COALESCE('.implode(', ', array_map(fn ($name) => $alias.'.'.$name, $names)).', '.$fallback.')' : $fallback;
    }

    private function excludeInvalid(Builder $query, string $table, string $alias): void
    {
        if (in_array('deleted_at', $this->columns($table), true)) { $query->whereNull($alias.'.deleted_at'); }
        if (in_array('status', $this->columns($table), true)) {
            $query->whereNotIn($alias.'.status', ['cancelled', 'canceled', 'void', 'deleted', 'failed', 'rejected']);
        }
    }

    public function query(array $filters): Builder
    {
        $query = DB::connection('mysql_loan')->table('loans as l');
        $this->excludeInvalid($query, 'loans', 'l');
        if (in_array('status', $this->columns('loans'), true)) { $query->where('l.status', '<>', 'draft'); }
        $loanDate = $this->first('loans', ['loan_date', 'disbursement_date', 'created_at']);
        if ($loanDate) { $query->whereDate('l.'.$loanDate, '<=', $filters['cutoff_date']); }
        $locationColumn = $this->first('loans', ['business_location_id', 'loan_business_location_id']);
        if ($filters['location_id'] && $locationColumn) { $query->where('l.'.$locationColumn, $filters['location_id']); }
        if ($filters['location_id'] && !$locationColumn) { $query->whereRaw('1 = 0'); }
        $customerJoin = $this->columns('loan_customers') && in_array('customer_id', $this->columns('loans'), true);
        if ($customerJoin) { $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id'); }
        $locationJoin = $this->columns('loan_business_locations') && $locationColumn;
        if ($locationJoin) { $query->leftJoin('loan_business_locations as loc', 'loc.id', '=', 'l.'.$locationColumn); }
        $fields = [
            'loan_number' => ['loan_number'], 'customer_name_snapshot' => ['customer_name_snapshot'],
            'customer_phone_snapshot' => ['customer_phone_snapshot'], 'currency' => ['currency'],
            'principal_amount' => ['principal_amount', 'financed_amount'], 'balance_amount' => ['balance_amount', 'amount_balance'],
            'status' => ['status'], 'disbursement_date' => ['disbursement_date', 'loan_date', 'created_at'],
            'maturity_date' => ['maturity_date'],
        ];
        $query->select('l.id');
        foreach ($fields as $field => $names) {
            $fallback = in_array($field, ['principal_amount', 'balance_amount'], true) ? '0' : ($field === 'currency' ? "'USD'" : 'NULL');
            $query->selectRaw($this->value('loans', 'l', $names, $fallback).' as '.$field);
        }
        foreach ([
            'customer_national_id' => ['id_card_number', 'national_id', 'passport_number'],
            'customer_dob' => ['dob', 'date_of_birth', 'birth_date'], 'customer_gender' => ['gender', 'sex'],
            'customer_province' => ['province'], 'customer_district' => ['district'],
            'customer_commune' => ['commune'], 'customer_village' => ['village'],
            'customer_name_english' => ['name', 'english_name'], 'customer_name_khmer' => ['khmer_name'],
        ] as $field => $names) {
            $query->selectRaw(($customerJoin ? $this->value('loan_customers', 'c', $names) : 'NULL').' as '.$field);
        }
        $query->selectRaw(($locationJoin ? $this->value('loan_business_locations', 'loc', ['name']) : 'NULL').' as location_name');
        if ($this->first('loan_payment_schedules', ['due_date']) && $this->first('loan_payment_schedules', ['loan_id'])) {
            $balance = $this->value('loan_payment_schedules', 's', ['balance_amount', 'amount_balance'], '0');
            $overdue = DB::connection('mysql_loan')->table('loan_payment_schedules as s')
                ->select('s.loan_id')->selectRaw('MIN(s.due_date) as oldest_due_date, SUM('.$balance.') as overdue_amount')
                ->whereDate('s.due_date', '<', $filters['snapshot_date'])->whereRaw($balance.' > 0')->groupBy('s.loan_id');
            $this->excludeInvalid($overdue, 'loan_payment_schedules', 's');
            if (in_array('status', $this->columns('loan_payment_schedules'), true)) {
                $overdue->whereNotIn('s.status', ['paid', 'confirmed', 'completed']);
            }
            $query->leftJoinSub($overdue, 'ov', 'ov.loan_id', '=', 'l.id')
                ->selectRaw('ov.oldest_due_date, COALESCE(ov.overdue_amount, 0) as overdue_amount');
        } else { $query->selectRaw('NULL as oldest_due_date, 0 as overdue_amount'); }
        $paymentDate = $this->first('loan_payments', ['paid_date', 'paid_on', 'payment_date', 'paid_at', 'created_at']);
        if ($paymentDate && $this->first('loan_payments', ['loan_id'])) {
            $amount = $this->value('loan_payments', 'p', ['total_paid', 'amount_paid', 'amount'], '0');
            $payments = DB::connection('mysql_loan')->table('loan_payments as p')->select('p.loan_id')
                ->selectRaw('MAX(p.'.$paymentDate.') as last_payment_date, SUM('.$amount.') as total_repaid')
                ->whereDate('p.'.$paymentDate, '<=', $filters['cutoff_date'])->groupBy('p.loan_id');
            $this->excludeInvalid($payments, 'loan_payments', 'p');
            $query->leftJoinSub($payments, 'lp', 'lp.loan_id', '=', 'l.id')
                ->selectRaw('lp.last_payment_date, COALESCE(lp.total_repaid, 0) as total_repaid');
        } else { $query->selectRaw('NULL as last_payment_date, 0 as total_repaid'); }
        if ($filters['search'] !== '') {
            $query->where(function ($q) use ($filters) {
                foreach (['loan_number', 'customer_name_snapshot', 'customer_phone_snapshot'] as $column) {
                    if (in_array($column, $this->columns('loans'), true)) { $q->orWhere('l.'.$column, 'like', '%'.$filters['search'].'%'); }
                }
            });
        }
        return $query->orderBy('l.id');
    }

    public function prepare(object $row, array $filters): object
    {
        $row->currency = strtoupper($row->currency ?: 'USD');
        $row->max_dpd = $row->oldest_due_date ? max(0, (int) Carbon::parse($row->oldest_due_date)->diffInDays(Carbon::parse($filters['snapshot_date']), false)) : 0;
        $row->dpd_bucket = $row->max_dpd === 0 ? 'Current' : ($row->max_dpd <= 30 ? '1-30 days' : ($row->max_dpd <= 60 ? '31-60 days' : ($row->max_dpd <= 90 ? '61-90 days' : '91+ days')));
        $row->missing_details = [];
        foreach (['customer_name_snapshot' => 'Name', 'customer_phone_snapshot' => 'Phone', 'customer_national_id' => 'ID', 'customer_dob' => 'Date of birth', 'customer_gender' => 'Gender'] as $field => $label) {
            if (trim((string) $row->$field) === '') { $row->missing_details[] = $label; }
        }
        return $row;
    }

    public function csvHeaders(): array
    {
        return ['Reporting Cycle', 'Payment Cutoff Date', 'Current Balance Snapshot Date', 'Loan Account Number',
            'Borrower Name (Khmer)', 'Borrower Name (English)', 'National ID / Passport', 'Date of Birth', 'Gender',
            'Phone Number', 'Province', 'District', 'Commune', 'Village', 'Branch', 'Currency', 'Loan Principal',
            'Disbursement Date', 'Maturity Date', 'Current Outstanding Balance', 'Current Past Due Amount',
            'Current Days Past Due', 'DPD Bucket', 'Current Account Status', 'Last Repayment Date',
            'Total Repaid Through Cutoff', 'Missing Details'];
    }

    public function csvRow(object $row, array $filters): array
    {
        $values = [$filters['month'], $filters['cutoff_date'], $filters['snapshot_date'], $row->loan_number,
            $row->customer_name_khmer, $row->customer_name_english, $row->customer_national_id,
            $row->customer_dob, $row->customer_gender, $row->customer_phone_snapshot,
            $row->customer_province, $row->customer_district, $row->customer_commune, $row->customer_village,
            $row->location_name, $row->currency, number_format((float) $row->principal_amount, 2, '.', ''),
            substr((string) $row->disbursement_date, 0, 10), $row->maturity_date,
            number_format((float) $row->balance_amount, 2, '.', ''), number_format((float) $row->overdue_amount, 2, '.', ''),
            $row->max_dpd, $row->dpd_bucket, $row->status, substr((string) $row->last_payment_date, 0, 10),
            number_format((float) $row->total_repaid, 2, '.', ''), implode('; ', $row->missing_details)];
        return array_map(function ($value) {
            $value = (string) ($value ?? '');
            return preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $value) ? "'".$value : $value;
        }, $values);
    }
}
