<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Concerns\FiltersExportQueries;
use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepaymentReportExporter implements EnterpriseExporter
{
    use FiltersExportQueries;

    public function headings(): array
    {
        return ['ID', 'Receipt No', 'Loan Number', 'Customer', 'Location', 'Amount', 'Penalty', 'Discount', 'Channel', 'Status', 'Paid At', 'Received By'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $query = DB::connection('mysql_loan')->table('loan_payments as p')
            ->leftJoin('loans as l', 'p.loan_id', '=', 'l.id')
            ->leftJoin('loan_business_locations as lbl', 'l.business_location_id', '=', 'lbl.id')
            ->select('p.id', 'p.payment_ref_no', 'l.loan_number', 'l.customer_name_snapshot', 'lbl.name as location', 'p.amount', 'p.penalty_amount', 'p.discount_amount', 'p.channel', 'p.status', 'p.paid_at', 'p.received_by_name_snapshot')
            ->orderBy('p.id');

        $this->applyBusinessFilter($query, $businessId);
        $this->applyDateFilter($query, $filters, 'p.paid_at');
        $this->applyLocationFilter($query, $filters, 'l.business_location_id');

        if (! empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        return $query;
    }

    public function map($row): array
    {
        return [$row->id, $row->payment_ref_no, $row->loan_number, $row->customer_name_snapshot, $row->location, $row->amount, $row->penalty_amount, $row->discount_amount, $row->channel, $row->status, $row->paid_at, $row->received_by_name_snapshot];
    }

    public function chunkColumn(): string
    {
        return 'p.id';
    }

    protected function applyBusinessFilter(Builder $query, int $businessId): void
    {
        if (Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_business_id')) {
            $query->where('lbl.main_business_id', $businessId);
        }
    }
}
