<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\LocalCashierReport\Services\FinancialDashboardReport;

class FinancialSalesController extends Controller
{
    public function __construct(private FinancialDashboardController $dashboard)
    {
    }

    public function costs(Request $request, FinancialDashboardReport $report)
    {
        [$businessId, $locations, $filters] = $this->dashboard->context($request);
        $user = $request->user();
        $allSales = $user->can('sell.view') || $user->can('direct_sell.view') || $user->can('direct_sell.access');
        abort_unless($allSales || $user->can('view_own_sell_only'), 403);
        $request->validate(['page' => 'nullable|integer|min:1|max:2000', 'per_page' => 'nullable|integer|in:-1,10,25,50,100',
            'sort' => 'nullable|in:sku,lots,product,units,purchase_price,selling_price,amount,profit_loss', 'direction' => 'nullable|in:asc,desc']);
        $perPage = (int) $request->input('per_page', 25);
        $sort = $request->input('sort', 'sku');
        $direction = $request->input('direction', 'desc');
        $connections = ['main' => config('database.default'), 'accessory' => config('accessory.database_connection', 'accessory'), 'service' => config('service.database_connection', 'service')];
        $rows = collect();
        foreach ($connections as $source => $connection) {
            if ($filters['source'] !== 'all' && $filters['source'] !== $source) continue;
            $ids = [];
            foreach ($locations->whereIn('id', $filters['location_ids']) as $location) {
                $id = (int) $location->id;
                if ($source === 'main' ? $id > 0 : ($id < -1 && (-$id % 2) === ($source === 'service' ? 1 : 0))) {
                    $ids[] = $source === 'main' ? $id : intdiv(-$id, 2);
                }
            }
            if (! $ids) continue;
            // Separate applications have independent user IDs.
            abort_if($source !== 'main' && ! $allSales, 403);
            $records = $report->costQuery($connection, $businessId, $ids, $filters, false, true);
            if (! $allSales) $records->where('t.created_by', $user->id);
            $records = $records->get();
            foreach ($records as $row) {
                $row->source = $source;
                $row->edit_url = null;
                $rows->push($row);
            }
        }
        $numeric = ['units', 'purchase_price', 'selling_price', 'amount', 'profit_loss'];
        $rows = $rows->sort(function ($a, $b) use ($sort, $direction, $numeric) {
            $order = in_array($sort, $numeric, true) ? $a->$sort <=> $b->$sort : strcmp((string) $a->$sort, (string) $b->$sort);
            return ($order ? ($direction === 'asc' ? $order : -$order) : (($b->id <=> $a->id) ?: strcmp($b->source, $a->source)));
        });
        $count = $rows->count();
        $amount = $rows->contains(fn ($row) => $row->amount === null) ? null : (float) $rows->sum('amount');
        $units = (float) $rows->sum('units');
        $lastPage = $perPage === -1 ? 1 : max(1, (int) ceil($count / $perPage));
        $page = min((int) $request->input('page', 1), $lastPage);
        $rows = ($perPage === -1 ? $rows : $rows->slice(($page - 1) * $perPage, $perPage))->values();

        return response()->json(['rows' => $rows, 'total' => $count, 'amount' => $amount, 'units' => $units,
            'page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage]);
    }

    public function index(Request $request)
    {
        [$businessId, , $filters] = $this->dashboard->context($request);
        $user = $request->user();
        $allSales = $user->can('sell.view') || $user->can('direct_sell.view') || $user->can('direct_sell.access');
        abort_unless($allSales || $user->can('view_own_sell_only'), 403);
        $request->validate(['page' => 'nullable|integer|min:1|max:2000', 'per_page' => 'nullable|integer|in:-1,10,25,50,100',
            'sort' => 'nullable|in:date,invoice,customer,location,units,amount', 'direction' => 'nullable|in:asc,desc']);
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');
        $sortColumns = ['date' => 'sale.transaction_date', 'invoice' => 'sale.invoice_no', 'customer' => 'customer.name',
            'location' => 'location.name', 'units' => 'sold.units', 'amount' => 'amount'];
        $perPage = (int) $request->input('per_page', 25);
        $ids = in_array($filters['source'], ['all', 'main'], true)
            ? array_values(array_filter($filters['location_ids'], fn ($id) => $id > 0)) : [];
        $quantity = "CASE WHEN t.type = 'sell_return' AND line.parent_sell_line_id IS NULL THEN line.quantity_returned ELSE line.quantity END";
        $lines = DB::table('transaction_sell_lines as line')
            ->join('transactions as t', 't.id', '=', 'line.transaction_id')
            ->where('t.business_id', $businessId)->whereIn('t.location_id', $ids)
            ->where('t.status', 'final')->whereIn('t.type', ['sell', 'sell_return'])
            ->whereBetween('t.transaction_date', [$filters['start_date'].' 00:00:00', $filters['end_date'].' 23:59:59'])
            ->whereRaw("COALESCE(line.children_type, '') NOT IN ('combo', 'modifier')")
            ->whereRaw("($quantity) > 0");
        if (! $allSales) $lines->where('t.created_by', $user->id);
        $lines->groupBy('t.id')->select('t.id')->selectRaw("SUM(CASE WHEN t.type = 'sell_return' THEN -($quantity) ELSE ($quantity) END) as units");
        $query = DB::table('transactions as sale')->joinSub($lines, 'sold', fn ($join) => $join->on('sold.id', '=', 'sale.id'))
            ->leftJoin('contacts as customer', 'customer.id', '=', 'sale.contact_id')
            ->leftJoin('business_locations as location', 'location.id', '=', 'sale.location_id');
        $summary = (clone $query)->selectRaw('COUNT(*) as count, COALESCE(SUM(sold.units), 0) as units')->first();
        $showAll = $perPage === -1;
        $limit = $showAll ? max(1, (int) $summary->count) : $perPage;
        $lastPage = $showAll ? 1 : max(1, (int) ceil($summary->count / $limit));
        $page = min((int) $request->input('page', 1), $lastPage);
        $rows = $query->select('sale.id', 'sale.transaction_date', 'sale.invoice_no', 'sale.type', 'sale.is_direct_sale', 'sale.created_by', 'customer.name as customer_name', 'location.name as location_name', 'sold.units')
            ->selectRaw("CASE WHEN sale.type = 'sell_return' THEN -sale.final_total ELSE sale.final_total END as amount")
            ->orderBy($sortColumns[$sort], $direction)->orderByDesc('sale.id')->offset(($page - 1) * $limit)->limit($limit)->get();

        foreach ($rows as $row) {
            $row->edit_url = null;
            if ($row->type === 'sell_return') {
                if ($user->can('access_sell_return') || ($user->can('access_own_sell_return') && (int) $row->created_by === (int) $user->id)) {
                    $row->edit_url = route('sell-return.edit', $row->id);
                }
            } elseif ($row->is_direct_sale && $user->can('direct_sell.update')) {
                $row->edit_url = route('sells.edit', $row->id);
            } elseif (! $row->is_direct_sale && $user->can('sell.update')) {
                $row->edit_url = route('pos.edit', $row->id);
            }
            unset($row->created_by, $row->is_direct_sale);
        }

        return response()->json(['rows' => $rows, 'total' => (int) $summary->count, 'units' => (float) $summary->units,
            'sort' => $sort, 'direction' => $direction, 'page' => $page, 'per_page' => $perPage, 'last_page' => $lastPage]);
    }
}
