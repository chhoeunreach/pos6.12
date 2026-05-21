<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Concerns\FiltersExportQueries;
use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryReportExporter implements EnterpriseExporter
{
    use FiltersExportQueries;

    public function headings(): array
    {
        return ['ID', 'Location', 'Product', 'SKU', 'Variation', 'Quantity Available', 'Updated At'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $query = DB::table('variation_location_details as vld')
            ->join('variations as v', 'vld.variation_id', '=', 'v.id')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->leftJoin('business_locations as bl', 'vld.location_id', '=', 'bl.id')
            ->where('p.business_id', $businessId)
            ->select('vld.id', 'bl.name as location', 'p.name as product', 'v.sub_sku', 'v.name as variation', 'vld.qty_available', 'vld.updated_at')
            ->orderBy('vld.id');

        $this->applyLocationFilter($query, $filters, 'vld.location_id');

        return $query;
    }

    public function map($row): array
    {
        return [$row->id, $row->location, $row->product, $row->sub_sku, $row->variation, $row->qty_available, $row->updated_at];
    }

    public function chunkColumn(): string
    {
        return 'vld.id';
    }
}
