<?php

namespace Modules\LocalCashierReport\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialDashboardReport
{
    public const METRICS = ['units', 'main_sales', 'accessory_sales', 'service_sales', 'sales', 'returns', 'net_sales', 'cost', 'gross_profit', 'gross_margin', 'expenses', 'net_profit', 'margin'];

    public function build(int $businessId, Collection $accessibleLocations, array $filters): array
    {
        $branches = [];
        $sourceLocations = [];
        foreach ($accessibleLocations->whereIn('id', $filters['location_ids']) as $location) {
            $filterId = (int) $location->id;
            $source = $filterId > 0 ? 'main' : ((-$filterId % 2) === 0 ? 'accessory' : 'service');
            if (($filters['source'] ?? 'all') !== 'all' && $filters['source'] !== $source) {
                continue;
            }
            $locationId = $filterId > 0 ? $filterId : intdiv(-$filterId, 2);
            $name = $source === 'main' ? $location->name : preg_replace('/ \((Accessory|Service)\)$/', '', $location->name);
            // Display, storage and module locations share the branch prefix.
            $prefix = trim(explode('-', $name, 2)[0]);
            $name = $prefix !== '' ? $prefix : trim($name);
            $key = mb_strtolower(trim($name));
            if (! isset($branches[$key])) {
                $branches[$key] = $this->emptyBranch(trim($name));
            }
            $branches[$key]['location_ids'][] = $filterId;
            $sourceLocations[$source][$locationId] = $key;
        }

        $connections = [
            'main' => config('database.default'),
            'accessory' => config('accessory.database_connection', 'accessory'),
            'service' => config('service.database_connection', 'service'),
        ];
        $expenseRows = [];
        $unavailable = [];
        foreach ($sourceLocations as $source => $locationMap) {
            try {
                $data = $this->sourceData($connections[$source], $businessId, array_keys($locationMap), $filters, $source !== 'main');
                foreach ($data['totals'] as $row) {
                    $branch = &$branches[$locationMap[(int) $row->location_id]];
                    $branch['invoices'] += (int) $row->invoices;
                    $branch['sales'] += (float) $row->sales;
                    $branch['returns'] += (float) $row->returns;
                    $branch['expenses'] += (float) $row->expenses;
                    if ($branch[$source.'_sales'] !== null) $branch[$source.'_sales'] += (float) $row->sales - (float) $row->returns;
                    $branch['missing_revenue'] += (int) ($row->missing_revenue ?? 0);
                    if (!empty($row->missing_revenue)) $branch[$source.'_sales'] = null;
                    $cost = $data['costs'][(int) $row->location_id] ?? null;
                    $branch['cost'] += (float) ($cost->cost ?? 0);
                    if ($source === 'main') $branch['units'] += (float) ($cost->units ?? 0);
                    $branch['missing_costs'] += (int) ($cost->missing ?? 0);
                    $branch['estimated_costs'] += (int) ($cost->estimated ?? 0);
                    if ((int) ($cost->costed_transactions ?? 0) < (int) $row->cost_transactions) {
                        $branch['missing_costs']++;
                        if ($source === 'main') $branch['missing_lines']++;
                    }
                    unset($branch);
                }
                foreach ($data['expenses'] as $row) {
                    $key = $row->category_name ?? '__uncategorized__';
                    $branchKey = $locationMap[(int) $row->location_id];
                    $expenseRows[$key]['name'] = $key;
                    $expenseRows[$key]['values'][$branchKey] = ($expenseRows[$key]['values'][$branchKey] ?? 0) + (float) $row->amount;
                    $expenseRows[$key]['total'] = ($expenseRows[$key]['total'] ?? 0) + (float) $row->amount;
                }
            } catch (\Throwable $e) {
                Log::warning('Financial dashboard source unavailable', ['source' => $source, 'business_id' => $businessId, 'error' => $e->getMessage()]);
                $unavailable[] = $source;
                foreach ($locationMap as $branchKey) {
                    $branches[$branchKey]['unavailable'] = true;
                    if ($source === 'main') $branches[$branchKey]['units_unavailable'] = true;
                }
            }
        }

        $expenseOptions = array_map('strval', array_keys($expenseRows));
        sort($expenseOptions, SORT_NATURAL | SORT_FLAG_CASE);
        $expenseFilterActive = ! empty($filters['excluded_expense_categories']) ||
            (! empty($filters['expense_filter_applied']) && count(array_diff($expenseOptions, $filters['expense_categories'] ?? [])) > 0);
        $expenseRows = array_filter($expenseRows, function ($row) use ($filters) {
            return (empty($filters['expense_filter_applied']) || in_array($row['name'], $filters['expense_categories'] ?? [], true))
                && ! in_array($row['name'], $filters['excluded_expense_categories'] ?? [], true);
        });
        foreach ($branches as &$branch) $branch['expenses'] = 0;
        unset($branch);
        foreach ($expenseRows as $expense) {
            foreach ($expense['values'] as $key => $amount) $branches[$key]['expenses'] += $amount;
        }

        $total = $this->emptyBranch('Total');
        foreach ($branches as &$branch) {
            foreach (['invoices', 'units', 'sales', 'returns', 'cost', 'expenses', 'main_sales', 'accessory_sales', 'service_sales', 'missing_costs', 'estimated_costs', 'missing_lines', 'missing_revenue'] as $metric) {
                $total[$metric] = $total[$metric] === null || $branch[$metric] === null ? null : $total[$metric] + $branch[$metric];
            }
            $total['unavailable'] = $total['unavailable'] || $branch['unavailable'];
            $total['units_unavailable'] = $total['units_unavailable'] || $branch['units_unavailable'];
            $branch = $this->calculate($branch);
        }
        unset($branch);
        if ($unavailable) $expenseRows = [];
        uasort($expenseRows, fn ($a, $b) => $b['total'] <=> $a['total']);
        $positiveExpenses = array_sum(array_map(fn ($row) => max(0, $row['total']), $expenseRows));
        foreach ($expenseRows as &$row) {
            $row['share'] = $positiveExpenses > 0 ? max(0, $row['total']) / $positiveExpenses * 100 : 0;
        }
        unset($row);

        $rankedBranches = array_filter($branches, fn ($branch) => $branch['units'] !== null && $branch['units'] > 0);
        uasort($rankedBranches, fn ($a, $b) => $b['units'] <=> $a['units']);

        return ['branches' => $branches, 'total' => $this->calculate($total), 'expenses' => $expenseRows,
            'expense_options' => $expenseOptions, 'expense_filter_active' => $expenseFilterActive,
            'unavailable' => $unavailable, 'top_location' => ($total['units_unavailable'] || $total['missing_lines'] > 0) ? null : (reset($rankedBranches) ?: null)];
    }

    private function emptyBranch(string $name): array
    {
        return array_fill_keys(['invoices', 'units', 'sales', 'returns', 'cost', 'expenses', 'main_sales', 'accessory_sales', 'service_sales', 'missing_costs', 'estimated_costs', 'missing_lines', 'missing_revenue'], 0)
            + ['name' => $name, 'location_ids' => [], 'unavailable' => false, 'units_unavailable' => false];
    }

    private function calculate(array $row): array
    {
        $posUnits = ($row['units_unavailable'] || $row['missing_lines'] > 0) ? null : $row['units'];
        $row['net_sales'] = $row['sales'] - $row['returns'];
        if ($row['unavailable']) {
            foreach (self::METRICS as $metric) {
                $row[$metric] = null;
            }
            $row['invoices'] = $row['gross_margin'] = null;
            $row['units'] = $posUnits;

            return $row;
        }
        if ($row['missing_lines'] > 0) $row['units'] = null;
        if ($row['missing_revenue'] > 0) {
            $row['sales'] = $row['returns'] = $row['net_sales'] = $row['gross_profit'] = $row['gross_margin'] = $row['net_profit'] = $row['margin'] = null;
            if ($row['missing_costs'] > 0) $row['cost'] = null;

            return $row;
        }
        if ($row['missing_costs'] > 0) {
            $row['cost'] = $row['gross_profit'] = $row['gross_margin'] = $row['net_profit'] = $row['margin'] = null;
        } else {
            $row['gross_profit'] = $row['net_sales'] - $row['cost'];
            $row['net_profit'] = $row['gross_profit'] - $row['expenses'];
            $row['margin'] = $row['net_sales'] > 0.00001 ? $row['net_profit'] / $row['net_sales'] * 100 : null;
            $row['gross_margin'] = $row['net_sales'] > 0.00001 ? $row['gross_profit'] / $row['net_sales'] * 100 : null;
        }

        return $row;
    }

    private function transactions(string $connection, int $businessId, array $locationIds, array $filters)
    {
        return DB::connection($connection)->table('transactions as t')
            ->where('t.business_id', $businessId)->whereIn('t.location_id', $locationIds)
            ->where('t.status', 'final')
            ->whereBetween('t.transaction_date', [$filters['start_date'].' 00:00:00', $filters['end_date'].' 23:59:59']);
    }

    private function sourceData(string $connection, int $businessId, array $locationIds, array $filters, bool $useSellingPrices = false): array
    {
        $base = $this->transactions($connection, $businessId, $locationIds, $filters);
        $totals = (clone $base)->whereIn('t.type', ['sell', 'sell_return', 'expense', 'expense_refund'])
            ->groupBy('t.location_id')->select('t.location_id')
            ->selectRaw("SUM(CASE WHEN t.type = 'sell' THEN 1 ELSE 0 END) as invoices")
            ->selectRaw("SUM(CASE WHEN t.type IN ('sell', 'sell_return') THEN 1 ELSE 0 END) as cost_transactions")
            ->selectRaw("SUM(CASE WHEN t.type = 'sell' THEN t.final_total ELSE 0 END) as sales")
            ->selectRaw("SUM(CASE WHEN t.type = 'sell_return' THEN t.final_total ELSE 0 END) as returns")
            ->selectRaw("SUM(CASE WHEN t.type = 'expense' THEN t.final_total WHEN t.type = 'expense_refund' THEN -t.final_total ELSE 0 END) as expenses")
            ->get();
        if ($useSellingPrices && $totals->sum('cost_transactions') > 0) {
            $sellingTotals = $this->sellingTotals($connection, $businessId, $locationIds, $filters);
            foreach ($totals as $row) {
                $selling = $sellingTotals[(int) $row->location_id] ?? null;
                $row->sales = (float) ($selling->sales ?? 0);
                $row->returns = (float) ($selling->returns ?? 0);
                $row->missing_revenue = (int) ($selling->missing_prices ?? 0)
                    + max(0, (int) $row->cost_transactions - (int) ($selling->line_transactions ?? 0));
            }
        }
        $expenses = $this->expenseQuery($connection, $businessId, $locationIds, $filters, false)
            ->select('t.location_id')->selectRaw('COALESCE(root.name, ec.name) as category_name')
            ->selectRaw("SUM(CASE WHEN t.type = 'expense_refund' THEN -t.final_total ELSE t.final_total END) as amount")
            ->groupBy('t.location_id', 'root.name', 'ec.name')->get();
        $costs = collect();
        if ($totals->sum('cost_transactions') > 0) {
            $costs = $this->costs($connection, $businessId, $locationIds, $filters);
        }

        return compact('totals', 'expenses', 'costs');
    }

    private function sellingTotals(string $connection, int $businessId, array $locationIds, array $filters)
    {
        $quantity = "CASE WHEN t.type = 'sell_return' AND line.parent_sell_line_id IS NULL THEN line.quantity_returned ELSE line.quantity END";
        // unit_price_inc_tax is the saved selling price after item discounts. A zero price is valid.
        $price = 'COALESCE(line.unit_price_inc_tax, CASE WHEN original_sale.id IS NOT NULL THEN original.unit_price_inc_tax END)';

        return $this->transactions($connection, $businessId, $locationIds, $filters)
            ->whereIn('t.type', ['sell', 'sell_return'])
            ->join('transaction_sell_lines as line', 'line.transaction_id', '=', 't.id')
            ->leftJoin('transaction_sell_lines as original', function ($join) {
                $join->on('original.id', '=', DB::raw("CASE WHEN t.type = 'sell_return' THEN line.parent_sell_line_id ELSE line.id END"));
            })
            ->leftJoin('transactions as original_sale', function ($join) {
                $join->on('original_sale.id', '=', 'original.transaction_id')->on('original_sale.business_id', '=', 't.business_id');
            })
            // Combo component prices allocate the parent's price and must not be counted again.
            ->whereRaw("COALESCE(line.children_type, original.children_type, '') <> 'combo'")
            ->whereRaw("($quantity) > 0")
            ->groupBy('t.location_id')->select('t.location_id')
            ->selectRaw('COUNT(DISTINCT t.id) as line_transactions')
            ->selectRaw("SUM(CASE WHEN t.type = 'sell' THEN ($quantity) * ($price) ELSE 0 END) as sales")
            ->selectRaw("SUM(CASE WHEN t.type = 'sell_return' THEN ($quantity) * ($price) ELSE 0 END) as returns")
            ->selectRaw("SUM(CASE WHEN ($price) IS NULL THEN 1 ELSE 0 END) as missing_prices")
            ->get()->keyBy('location_id');
    }

    public function expenseQuery(string $connection, int $businessId, array $locationIds, array $filters, bool $applyCategories = true)
    {
        $query = $this->transactions($connection, $businessId, $locationIds, $filters)
            ->whereIn('t.type', ['expense', 'expense_refund'])
            ->leftJoin('expense_categories as ec', function ($join) {
                $join->on('ec.id', '=', 't.expense_category_id')->on('ec.business_id', '=', 't.business_id');
            })->leftJoin('expense_categories as root', function ($join) {
                $join->on('root.id', '=', 'ec.parent_id')->on('root.business_id', '=', 't.business_id');
            });
        if ($applyCategories) {
            $category = DB::raw("COALESCE(root.name, ec.name, '__uncategorized__')");
            if (! empty($filters['expense_filter_applied'])) $query->whereIn($category, $filters['expense_categories'] ?? []);
            if (! empty($filters['excluded_expense_categories'])) $query->whereNotIn($category, $filters['excluded_expense_categories']);
        }

        return $query;
    }

    private function costs(string $connection, int $businessId, array $locationIds, array $filters)
    {
        return $this->costQuery($connection, $businessId, $locationIds, $filters)->get()->keyBy('location_id');
    }

    public function costQuery(string $connection, int $businessId, array $locationIds, array $filters, bool $byTransaction = false, bool $byLine = false)
    {
        $db = DB::connection($connection);
        $allocations = $db->table('transaction_sell_lines_purchase_lines as a')
            ->leftJoin('purchase_lines as p', 'p.id', '=', 'a.purchase_line_id')
            ->leftJoin('transactions as pt', 'pt.id', '=', 'p.transaction_id')
            ->join('transaction_sell_lines as allocated_line', 'allocated_line.id', '=', 'a.sell_line_id')
            ->whereColumn('p.variation_id', 'allocated_line.variation_id')
            ->where('a.quantity', '>', 0)->where('pt.business_id', $businessId)
            ->groupBy('a.sell_line_id')->select('a.sell_line_id')
            ->selectRaw('SUM(a.quantity) as quantity, SUM(a.quantity * p.purchase_price_inc_tax) as cost')
            ->selectRaw('SUM(CASE WHEN p.purchase_price_inc_tax IS NOT NULL THEN a.quantity ELSE 0 END) as costed_quantity');
        if ($byLine) $allocations->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(p.lot_number, '')) as lots, GROUP_CONCAT(DISTINCT p.id) as purchase_ids");
        // Return lines point at the original sale, preserving its historical cost.
        $query = $this->transactions($connection, $businessId, $locationIds, $filters)
            ->whereIn('t.type', ['sell', 'sell_return'])
            ->join('transaction_sell_lines as line', 'line.transaction_id', '=', 't.id')
            ->leftJoin('transaction_sell_lines as sold', function ($join) {
                $join->on('sold.id', '=', DB::raw("CASE WHEN t.type = 'sell_return' THEN COALESCE(line.parent_sell_line_id, line.id) ELSE line.id END"));
            })
            ->leftJoin('transactions as sale', function ($join) {
                $join->on('sale.id', '=', 'sold.transaction_id')->on('sale.business_id', '=', 't.business_id');
            })
            ->leftJoin('products as product', function ($join) {
                $join->on('product.id', '=', 'sold.product_id')->on('product.business_id', '=', 't.business_id');
            })
            ->leftJoin('variations as v', function ($join) {
                $join->on('v.id', '=', 'sold.variation_id')->on('v.product_id', '=', 'product.id');
            })
            ->leftJoin('purchase_lines as lot', function ($join) {
                $join->on('lot.id', '=', 'sold.lot_no_line_id')->on('lot.variation_id', '=', 'sold.variation_id');
            })
            ->leftJoin('transactions as lot_tx', function ($join) {
                $join->on('lot_tx.id', '=', 'lot.transaction_id')->on('lot_tx.business_id', '=', 't.business_id');
            })
            ->leftJoinSub($allocations, 'allocated', fn ($join) => $join->on('allocated.sell_line_id', '=', 'sold.id'));
        $recorded = "COALESCE(
            CASE WHEN lot_tx.id IS NOT NULL THEN lot.purchase_price_inc_tax END,
            CASE WHEN ABS(allocated.quantity - sold.quantity) < 0.00001 AND ABS(allocated.costed_quantity - allocated.quantity) < 0.00001 THEN allocated.cost / NULLIF(allocated.quantity, 0) END
        )";
        $historical = "COALESCE($recorded,
            (SELECT recent.purchase_price_inc_tax FROM purchase_lines as recent
                INNER JOIN transactions as purchase ON purchase.id = recent.transaction_id
                WHERE recent.variation_id = sold.variation_id AND purchase.business_id = t.business_id
                    AND purchase.location_id = sale.location_id AND purchase.status = 'received'
                    AND purchase.type IN ('purchase', 'opening_stock', 'production_purchase')
                    AND purchase.transaction_date <= sale.transaction_date AND recent.purchase_price_inc_tax IS NOT NULL
                ORDER BY purchase.transaction_date DESC, recent.id DESC LIMIT 1)
        )";
        $unitCost = "CASE WHEN product.id IS NULL OR sale.id IS NULL THEN NULL WHEN product.enable_stock = 0 THEN 0 ELSE COALESCE($historical, v.dpp_inc_tax, v.default_purchase_price) END";
        $quantity = "CASE WHEN t.type = 'sell_return' AND line.parent_sell_line_id IS NULL THEN line.quantity_returned ELSE line.quantity END";

        if ($byLine) {
            $signedQuantity = "CASE WHEN t.type = 'sell_return' THEN -($quantity) ELSE ($quantity) END";
            $sellingPrice = "CASE WHEN COALESCE(line.children_type, sold.children_type, '') = 'combo' THEN 0 ELSE COALESCE(line.unit_price_inc_tax, CASE WHEN sale.id IS NOT NULL THEN sold.unit_price_inc_tax END) END";

            return $query->whereRaw("($quantity) > 0")
                ->select('line.id', 't.id as transaction_id', 't.location_id', 't.transaction_date', 't.invoice_no', 'product.name as product', 'v.name as variation_name')
                ->selectRaw("COALESCE(NULLIF(v.sub_sku, ''), product.sku) as sku, COALESCE(NULLIF(lot.lot_number, ''), allocated.lots) as lots")
                ->addSelect('product.enable_stock', 'sold.variation_id')
                ->selectRaw("CASE WHEN lot_tx.id IS NOT NULL THEN CAST(lot.id AS CHAR)
                    WHEN ABS(allocated.quantity - sold.quantity) < 0.00001 THEN allocated.purchase_ids
                    ELSE (SELECT CAST(recent.id AS CHAR) FROM purchase_lines as recent
                        INNER JOIN transactions as purchase ON purchase.id = recent.transaction_id
                        WHERE recent.variation_id = sold.variation_id AND purchase.business_id = t.business_id
                            AND purchase.location_id = sale.location_id AND purchase.status = 'received'
                            AND purchase.type IN ('purchase', 'opening_stock', 'production_purchase')
                            AND purchase.transaction_date <= sale.transaction_date AND recent.purchase_price_inc_tax IS NOT NULL
                        ORDER BY purchase.transaction_date DESC, recent.id DESC LIMIT 1) END as purchase_line_ids")
                ->selectRaw("($signedQuantity) as units, ($unitCost) as purchase_price, ($sellingPrice) as selling_price")
                ->selectRaw("($signedQuantity) * ($unitCost) as amount, ($signedQuantity) * ($sellingPrice) as selling_total")
                ->selectRaw("($signedQuantity) * (($sellingPrice) - ($unitCost)) as profit_loss")
                ->selectRaw("CASE WHEN ($unitCost) IS NULL THEN 1 ELSE 0 END as missing")
                ->selectRaw("CASE WHEN product.enable_stock = 1 AND ($recorded) IS NULL AND COALESCE($historical, v.dpp_inc_tax, v.default_purchase_price) IS NOT NULL THEN 1 ELSE 0 END as estimated");
        }

        $query->whereRaw("($quantity) > 0")->groupBy('t.location_id')->select('t.location_id');
        if ($byTransaction) $query->groupBy('t.id')->addSelect('t.id as transaction_id');

        return $query
            ->selectRaw('COUNT(DISTINCT t.id) as costed_transactions')
            ->selectRaw("SUM(CASE WHEN line.children_type IN ('combo', 'modifier') THEN 0 WHEN t.type = 'sell_return' THEN -($quantity) ELSE ($quantity) END) as units")
            ->selectRaw("SUM(CASE WHEN t.type = 'sell_return' THEN -1 ELSE 1 END * ($quantity) * ($unitCost)) as cost")
            ->selectRaw("SUM(CASE WHEN ($unitCost) IS NULL THEN 1 ELSE 0 END) as missing")
            ->selectRaw("SUM(CASE WHEN product.enable_stock = 1 AND ($recorded) IS NULL AND COALESCE($historical, v.dpp_inc_tax, v.default_purchase_price) IS NOT NULL THEN 1 ELSE 0 END) as estimated");
    }
}
