<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Concerns\FiltersExportQueries;
use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanReportExporter implements EnterpriseExporter
{
    use FiltersExportQueries;

    public function headings(): array
    {
        return ['ID', 'Loan Number', 'Customer', 'Phone', 'Location', 'Principal', 'Interest', 'Total', 'Paid', 'Balance', 'Status', 'Loan Date'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $query = DB::connection('mysql_loan')->table('loans as l')
            ->leftJoin('loan_business_locations as lbl', 'l.business_location_id', '=', 'lbl.id')
            ->select('l.id', 'l.loan_number', 'l.customer_name_snapshot', 'l.customer_phone_snapshot', 'lbl.name as location', 'l.principal_amount', 'l.interest_amount', 'l.total_amount', 'l.paid_amount', 'l.balance_amount', 'l.status', 'l.loan_date')
            ->orderBy('l.id');

        $this->applyBusinessFilter($query, $businessId);
        $this->applyDateFilter($query, $filters, 'l.loan_date');
        $this->applyLocationFilter($query, $filters, 'l.business_location_id');

        if (! empty($filters['status'])) {
            $query->where('l.status', $filters['status']);
        }

        return $query;
    }

    public function map($row): array
    {
        return [$row->id, $row->loan_number, $row->customer_name_snapshot, $row->customer_phone_snapshot, $row->location, $row->principal_amount, $row->interest_amount, $row->total_amount, $row->paid_amount, $row->balance_amount, $row->status, $row->loan_date];
    }

    public function chunkColumn(): string
    {
        return 'l.id';
    }

    protected function applyBusinessFilter(Builder $query, int $businessId): void
    {
        if (Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_business_id')) {
            $query->where('lbl.main_business_id', $businessId);
        }
    }
}
