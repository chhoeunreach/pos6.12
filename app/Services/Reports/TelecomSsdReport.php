<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TelecomSsdReport
{
    public const HEADINGS = ['N°', 'Product Name', 'Model Number', 'QA Number', 'Trademark', 'Country of Manufacture', 'SIM', 'Quantity', 'Unit Price', 'Total Price', 'Date', 'Client Name', 'Remark'];

    public function query(int $businessId, $permittedLocations, array $filters): Builder
    {
        $quantity = '(pl.quantity - COALESCE(pl.quantity_returned, 0))';
        // Older installations may have product fields before purchase fields,
        // or neither migration yet. Never select columns that do not exist.
        $purchaseColumns = Schema::getColumnListing('purchase_lines');
        $productColumns = Schema::getColumnListing('products');
        $metadata = [];
        foreach (['model_number' => 'model_number', 'qa_number' => 'qa_number',
            'country_of_manufacture' => 'country_of_manufacture', 'sim' => 'network_connectivity'] as $alias => $column) {
            $sources = [];
            if (in_array($column, $purchaseColumns, true)) {
                $sources[] = 'pl.' . $column;
            }
            if (in_array($column, $productColumns, true)) {
                $sources[] = 'p.' . $column;
            }
            $metadata[$alias] = count($sources) > 1
                ? 'COALESCE(' . implode(', ', $sources) . ')'
                : ($sources[0] ?? 'NULL');
        }
        $client = "COALESCE(NULLIF(c.supplier_business_name, ''), c.name)";
        $query = DB::table('purchase_lines as pl')
            ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
            ->join('products as p', 'pl.product_id', '=', 'p.id')
            ->leftJoin('brands as b', function ($join) use ($businessId) {
                $join->on('p.brand_id', '=', 'b.id')->where('b.business_id', $businessId);
            })
            ->leftJoin('contacts as c', function ($join) use ($businessId) {
                $join->on('t.contact_id', '=', 'c.id')->where('c.business_id', $businessId);
            })
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('t.type', 'purchase')
            ->where('t.status', 'received')
            ->whereRaw($quantity . ' > 0')
            ->select('pl.id', 'p.name as product_name', 'b.name as trademark',
                'pl.purchase_price_inc_tax as unit_price', 't.transaction_date', 't.additional_notes as remark')
            ->selectRaw($quantity . ' as quantity')
            ->selectRaw($quantity . ' * pl.purchase_price_inc_tax as total_price')
            ->selectRaw($client . ' as client_name');

        foreach ($metadata as $alias => $expression) {
            $query->selectRaw($expression . ' as ' . $alias);
        }
        if ($permittedLocations !== 'all') {
            $query->whereIn('t.location_id', $permittedLocations);
        }
        foreach (['location_id' => 't.location_id', 'supplier_id' => 't.contact_id', 'brand_id' => 'p.brand_id', 'category_id' => 'p.category_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['start_date'])) {
            $query->where('t.transaction_date', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (! empty($filters['end_date'])) {
            $query->where('t.transaction_date', '<', \Carbon\Carbon::parse($filters['end_date'])->addDay()->toDateString() . ' 00:00:00');
        }
        if (filled($filters['search_text'] ?? null)) {
            $term = '%' . trim($filters['search_text']) . '%';
            $query->where(function ($query) use ($term, $metadata, $client) {
                $query->where('p.name', 'like', $term)
                    ->orWhere('b.name', 'like', $term)
                    ->orWhere('t.additional_notes', 'like', $term)
                    ->orWhere(DB::raw($client), 'like', $term);
                foreach ($metadata as $expression) {
                    $query->orWhere(DB::raw($expression), 'like', $term);
                }
            });
        }

        return $query;
    }

    public function applyOrder(Builder $query, array $orders): Builder
    {
        // Use our selected aliases, never column names supplied by the browser.
        $columns = [1 => 'product_name', 2 => 'model_number', 3 => 'qa_number',
            4 => 'trademark', 5 => 'country_of_manufacture', 6 => 'sim',
            7 => 'quantity', 8 => 'unit_price', 9 => 'total_price',
            10 => 'transaction_date', 11 => 'client_name', 12 => 'remark'];
        $ordered = false;
        foreach ($orders as $order) {
            $column = $columns[$order['column'] ?? -1] ?? null;
            $direction = $order['dir'] ?? '';
            if ($column !== null && in_array($direction, ['asc', 'desc'], true)) {
                $query->orderBy($column, $direction);
                $ordered = true;
            }
        }
        if (! $ordered) {
            $query->orderByDesc('t.transaction_date');
        }

        return $query->orderByDesc('pl.id');
    }

    public function csvRow($row, int $number): array
    {
        $values = [$number, $row->product_name, $row->model_number, $row->qa_number,
            $row->trademark, $row->country_of_manufacture, $row->sim, $row->quantity,
            $row->unit_price, $row->total_price, substr($row->transaction_date, 0, 10), $row->client_name, $row->remark];

        return array_map(function ($value) {
            // Keep user-entered text from being interpreted as spreadsheet formulas.
            return is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'" . $value : $value;
        }, $values);
    }
}
