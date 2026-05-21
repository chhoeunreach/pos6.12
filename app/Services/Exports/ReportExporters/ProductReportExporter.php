<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductReportExporter implements EnterpriseExporter
{
    public function headings(): array
    {
        return ['ID', 'Name', 'SKU', 'Type', 'Brand', 'Category', 'Unit', 'Manage Stock', 'Active'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        return DB::table('products as p')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->where('p.business_id', $businessId)
            ->select('p.id', 'p.name', 'p.sku', 'p.type', 'b.name as brand', 'c.name as category', 'u.short_name as unit', 'p.enable_stock', 'p.is_inactive')
            ->orderBy('p.id');
    }

    public function map($row): array
    {
        return [$row->id, $row->name, $row->sku, $row->type, $row->brand, $row->category, $row->unit, $row->enable_stock ? 'Yes' : 'No', $row->is_inactive ? 'No' : 'Yes'];
    }

    public function chunkColumn(): string
    {
        return 'p.id';
    }
}
