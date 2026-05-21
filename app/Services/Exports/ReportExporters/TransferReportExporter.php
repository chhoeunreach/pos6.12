<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Concerns\FiltersExportQueries;
use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TransferReportExporter implements EnterpriseExporter
{
    use FiltersExportQueries;

    public function headings(): array
    {
        return ['ID', 'Reference No', 'Date', 'From/Location', 'Status', 'Final Total', 'Shipping Status'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $query = DB::table('transactions as t')
            ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.business_id', $businessId)
            ->whereIn('t.type', ['stock_transfer', 'sell_transfer', 'purchase_transfer'])
            ->select('t.id', 't.ref_no', 't.transaction_date', 'bl.name as location', 't.status', 't.final_total', 't.shipping_status')
            ->orderBy('t.id');

        $this->applyDateFilter($query, $filters, 't.transaction_date');
        $this->applyLocationFilter($query, $filters, 't.location_id');

        return $query;
    }

    public function map($row): array
    {
        return [$row->id, $row->ref_no, $row->transaction_date, $row->location, $row->status, $row->final_total, $row->shipping_status];
    }

    public function chunkColumn(): string
    {
        return 't.id';
    }
}
