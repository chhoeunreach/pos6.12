<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Concerns\FiltersExportQueries;
use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SalesReportExporter implements EnterpriseExporter
{
    use FiltersExportQueries;

    public function headings(): array
    {
        return ['ID', 'Invoice No', 'Date', 'Customer', 'Location', 'Status', 'Payment Status', 'Final Total', 'Created By'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $query = DB::table('transactions as t')
            ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
            ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->select('t.id', 't.invoice_no', 't.transaction_date', 'c.name as customer', 'bl.name as location', 't.status', 't.payment_status', 't.final_total', DB::raw("CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as created_by_name"))
            ->orderBy('t.id');

        $this->applyDateFilter($query, $filters, 't.transaction_date');
        $this->applyLocationFilter($query, $filters, 't.location_id');

        if (! empty($filters['status'])) {
            $query->where('t.status', $filters['status']);
        }

        return $query;
    }

    public function map($row): array
    {
        return [$row->id, $row->invoice_no, $row->transaction_date, $row->customer, $row->location, $row->status, $row->payment_status, $row->final_total, trim($row->created_by_name)];
    }

    public function chunkColumn(): string
    {
        return 't.id';
    }
}
