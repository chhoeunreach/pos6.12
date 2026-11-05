<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\Events\PurchaseCreatedOrModified;
use App\Transaction;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\LocalCashierReport\Services\FinancialDashboardReport;
use Modules\LocalCashierReport\Support\ReportLanguage;
use Spatie\Activitylog\Models\Activity;

class FinancialPurchaseCostController extends Controller
{
    public function __construct(private FinancialDashboardController $dashboard, private FinancialDashboardReport $report, private ModuleUtil $modules)
    {
    }

    protected function scope(Request $request, string $source, int $line): array
    {
        [$business, $locations, $filters] = $this->dashboard->context($request);
        $user = $request->user();
        abort_unless($user->can('purchase.update'), 403);
        $allSales = $user->can('sell.view') || $user->can('direct_sell.view') || $user->can('direct_sell.access');
        abort_unless($allSales || ($source === 'main' && $user->can('view_own_sell_only')), 403);
        $connections = ['main' => config('database.default'), 'accessory' => config('accessory.database_connection', 'accessory'), 'service' => config('service.database_connection', 'service')];
        abort_unless(isset($connections[$source]), 404);
        abort_unless($filters['source'] === 'all' || $filters['source'] === $source, 404);
        $allowed = [];
        $selected = [];
        foreach ($locations as $location) {
            $id = (int) $location->id;
            if ($source === 'main' ? $id > 0 : ($id < -1 && (-$id % 2) === ($source === 'service' ? 1 : 0))) {
                $rawId = $source === 'main' ? $id : intdiv(-$id, 2);
                $allowed[] = $rawId;
                if (in_array($id, $filters['location_ids'], true)) $selected[] = $rawId;
            }
        }
        $connection = $connections[$source];
        $query = $this->report->costQuery($connection, $business, $selected, $filters, false, true)->where('line.id', $line);
        if (!$allSales) $query->where('t.created_by', $user->id);
        $row = $query->first();
        abort_unless($row && $row->enable_stock && $row->purchase_line_ids, 404);
        $ids = array_values(array_unique(array_map('intval', explode(',', $row->purchase_line_ids))));
        sort($ids);

        return [$business, $connection, $allowed, $ids];
    }

    private function lots(string $connection, int $business, array $allowed, array $ids, bool $lock = false)
    {
        $query = DB::connection($connection)->table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.business_id', $business)->whereIn('t.location_id', $allowed)
            ->whereIn('t.type', ['purchase', 'opening_stock', 'production_purchase'])->where('t.status', 'received')->whereIn('p.id', $ids)
            ->select('p.*', 't.ref_no as purchase_reference')->orderBy('p.id');
        if ($lock) $query->lockForUpdate();
        $lots = $query->get();
        abort_unless($lots->count() === count($ids), 404);

        return $lots;
    }

    private function version($lots): string
    {
        return hash('sha256', json_encode($lots->all()));
    }

    public function edit(Request $request, string $sourceName, int $line)
    {
        [$business, $connection, $allowed, $ids] = $this->scope($request, $sourceName, $line);
        $lots = $this->lots($connection, $business, $allowed, $ids);

        return response()->json(['lots' => $lots->map(fn ($lot) => ['id' => $lot->id, 'lot' => $lot->lot_number, 'reference' => $lot->purchase_reference, 'purchase_price' => $lot->purchase_price_inc_tax]),
            'cost_version' => $this->version($lots), 'update_url' => $request->fullUrl()]);
    }

    public function update(Request $request, string $sourceName, int $line)
    {
        [$business, $connection, $allowed, $ids] = $this->scope($request, $sourceName, $line);
        abort_unless($this->modules->isSubscribed($business), 403);
        $data = $request->validate(['cost_version' => 'required|string|size:64', 'prices' => 'required|array|min:1|max:100',
            'prices.*.id' => 'required|integer|distinct', 'prices.*.purchase_price' => 'required|numeric|min:0|max:999999999999']);
        $submittedIds = array_map('intval', array_column($data['prices'], 'id'));
        sort($submittedIds);
        abort_unless($submittedIds === $ids, 422);
        $db = DB::connection($connection);
        $db->transaction(function () use ($db, $connection, $business, $allowed, $ids, $data, $sourceName, $request) {
            $initial = $this->lots($connection, $business, $allowed, $ids);
            $transactions = $db->table('transactions')->whereIn('id', $initial->pluck('transaction_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lots = $this->lots($connection, $business, $allowed, $ids, true);
            abort_unless(hash_equals($this->version($lots), $data['cost_version']), 409, ReportLanguage::text('finance_cost_stale'));
            $before = $lots->map(fn ($lot) => (array) $lot)->all();
            $prices = collect($data['prices'])->keyBy('id');
            foreach ($lots as $lot) {
                $incTax = round((float) $prices[$lot->id]['purchase_price'], 4);
                $discount = (float) ($lot->discount_percent ?? 0);
                abort_if($discount >= 100 && $incTax > 0, 422, ReportLanguage::text('finance_cost_full_edit'));
                $rate = (float) $lot->purchase_price > 0 ? max(0, (float) $lot->purchase_price_inc_tax / (float) $lot->purchase_price - 1) : 0;
                if ((float) $lot->purchase_price <= 0 && !empty($lot->tax_id)) {
                    $rate = (float) $db->table('tax_rates')->where('business_id', $business)->where('id', $lot->tax_id)->value('amount') / 100;
                }
                $base = round($incTax / (1 + $rate), 4);
                $db->table('purchase_lines')->where('id', $lot->id)->update(['purchase_price_inc_tax' => $incTax, 'purchase_price' => $base,
                    'pp_without_discount' => $discount >= 100 ? 0 : round($base / (1 - $discount / 100), 4), 'item_tax' => round($incTax - $base, 4), 'updated_at' => now()]);
            }
            foreach ($transactions as $purchase) {
                $allLines = $db->table('purchase_lines')->where('transaction_id', $purchase->id)->get();
                $oldSubtotal = $allLines->sum(fn ($row) => $row->quantity * $row->purchase_price_inc_tax);
                foreach ($lots->where('transaction_id', $purchase->id) as $old) $oldSubtotal += $old->quantity * ((float) $old->purchase_price_inc_tax - round((float) $prices[$old->id]['purchase_price'], 4));
                $subtotal = $allLines->sum(fn ($row) => $row->quantity * $row->purchase_price_inc_tax);
                $discountValue = fn ($total) => $purchase->discount_type === 'percentage' ? $total * (float) $purchase->discount_amount / 100 : (float) $purchase->discount_amount;
                $oldTaxable = $oldSubtotal - $discountValue($oldSubtotal);
                $taxRate = $oldTaxable > 0 ? (float) $purchase->tax_amount / $oldTaxable : 0;
                if ($oldTaxable <= 0 && !empty($purchase->tax_id)) $taxRate = (float) $db->table('tax_rates')->where('business_id', $business)->where('id', $purchase->tax_id)->value('amount') / 100;
                abort_if($subtotal - $discountValue($subtotal) < -.00001, 422, ReportLanguage::text('finance_cost_full_edit'));
                $tax = ($subtotal - $discountValue($subtotal)) * $taxRate;
                $final = $subtotal - $discountValue($subtotal) + $tax + (float) $purchase->shipping_charges;
                for ($n = 1; $n <= 4; $n++) $final += (float) ($purchase->{'additional_expense_value_'.$n} ?? 0);
                $final = round($final, 4);
                $paid = (float) $db->table('transaction_payments')->where('transaction_id', $purchase->id)->selectRaw('COALESCE(SUM(CASE WHEN is_return = 0 THEN amount ELSE -amount END), 0) as paid')->value('paid');
                $db->table('transactions')->where('id', $purchase->id)->update(['total_before_tax' => round($allLines->sum(fn ($row) => $row->quantity * $row->purchase_price), 4),
                    'tax_amount' => round($tax, 4), 'final_total' => round($final, 4), 'payment_status' => $final <= $paid ? 'paid' : ($paid > 0 ? 'partial' : 'due'), 'updated_at' => now()]);
                $activity = new Activity;
                $activity->setConnection($connection);
                $activity->log_name = 'default';
                $activity->description = 'edited';
                $activity->subject_type = Transaction::class;
                $activity->subject_id = $purchase->id;
                $activity->business_id = $business;
                $activity->properties = ['dashboard_actor_id' => $request->user()->id, 'dashboard_source' => 'main', 'purchase_source' => $sourceName,
                    'old' => ['purchase_costs' => array_values(array_filter($before, fn ($row) => $row['transaction_id'] == $purchase->id))],
                    'attributes' => ['purchase_costs' => $allLines->whereIn('id', $ids)->values()->all()]];
                $activity->save();
                DB::usingConnection($connection, function () use ($connection, $purchase) {
                    PurchaseCreatedOrModified::dispatch(Transaction::on($connection)->findOrFail($purchase->id));
                });
            }
        });

        return response()->json(['success' => true]);
    }
}
