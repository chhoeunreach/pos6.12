<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Concerns\FiltersExportQueries;
use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImeiReportExporter implements EnterpriseExporter
{
    use FiltersExportQueries;

    public function headings(): array
    {
        return ['ID', 'Loan Number', 'Customer', 'Product', 'SKU', 'IMEI', 'Qty', 'Unit Price', 'Line Total', 'Created At'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $query = DB::connection('mysql_loan')->table('loan_items as i')
            ->leftJoin('loans as l', 'i.loan_id', '=', 'l.id')
            ->leftJoin('loan_business_locations as lbl', 'l.business_location_id', '=', 'lbl.id')
            ->select('i.id', 'l.loan_number', 'l.customer_name_snapshot', 'i.product_name_snapshot', 'i.sku_snapshot', 'i.imei_snapshot', 'i.qty', 'i.unit_price', 'i.line_total', 'i.created_at')
            ->whereNotNull('i.imei_snapshot')
            ->orderBy('i.id');

        $this->applyBusinessFilter($query, $businessId);
        $this->applyDateFilter($query, $filters, 'i.created_at');
        $this->applyLocationFilter($query, $filters, 'l.business_location_id');

        return $query;
    }

    public function map($row): array
    {
        return [$row->id, $row->loan_number, $row->customer_name_snapshot, $row->product_name_snapshot, $row->sku_snapshot, $row->imei_snapshot, $row->qty, $row->unit_price, $row->line_total, $row->created_at];
    }

    public function chunkColumn(): string
    {
        return 'i.id';
    }

    protected function applyBusinessFilter(Builder $query, int $businessId): void
    {
        if (Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_business_id')) {
            $query->where('lbl.main_business_id', $businessId);
        }
    }
}
