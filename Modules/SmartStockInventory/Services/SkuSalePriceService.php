<?php

namespace Modules\SmartStockInventory\Services;

use App\Events\SellCreatedOrModified;
use App\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\SmartStockActionLog;

class SkuSalePriceService
{
    public function context(int $business, int $variation, int $transaction, array $locations, $user, bool $lock = false): array
    {
        abort_unless($user->can('stock_inventory.view') && $user->can('sell.update'), 403);
        $query = DB::table('transactions')->where('business_id', $business)->whereIn('location_id', $locations)
            ->where('type', 'sell')->where('status', 'final')->where('id', $transaction)
            ->where(fn ($q) => $q->whereNull('sub_type')->orWhere('sub_type', '!=', 'project_invoice'));
        $ownOnly = $user->can('view_own_sell_only') || $user->can('access_own_shipping');
        $agentOnly = $user->can('view_commission_agent_sell') || $user->can('access_commission_agent_shipping');
        if (!$user->can('direct_sell.view') && ($ownOnly || $agentOnly)) {
            $query->where(function ($scope) use ($ownOnly, $agentOnly, $user) {
                if ($ownOnly) $scope->where('created_by', $user->id);
                if ($agentOnly) $scope->orWhere('commission_agent', $user->id);
            });
        }
        $sale = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($sale, 404);
        abort_if(($sale->zatca_status ?? '') === 'success', 422, 'This invoice has been synced and cannot be edited.');
        $days = session('business.transaction_edit_days');
        abort_if($days !== null && Carbon::parse($sale->transaction_date)->addDays((int) $days)->lt(today()), 422, 'The sale is outside the allowed editing period.');
        $query = DB::table('transaction_sell_lines')->where('transaction_id', $transaction)->orderBy('id');
        $all = ($lock ? $query->lockForUpdate() : $query)->get();
        $lines = $all->where('variation_id', $variation)->filter(fn ($line) => $line->parent_sell_line_id === null)->values();
        abort_if($lines->isEmpty(), 404);
        abort_if($all->contains(fn ($line) => (float) $line->quantity_returned > 0)
            || DB::table('transactions')->where('business_id', $business)->where('type', 'sell_return')->where('return_parent_id', $transaction)->exists(), 422, 'This sale has returns. Edit the sale and its returns together.');

        return [$sale, $lines];
    }

    public function version($sale, $lines): string
    {
        return hash('sha256', json_encode([$sale, $lines->all()]));
    }

    public function attach($rows, int $business, int $variation, array $locations)
    {
        $ids = collect($rows)->pluck('transaction_id')->unique()->all();
        $lines = DB::table('transaction_sell_lines as sl')->join('transactions as t', 't.id', '=', 'sl.transaction_id')
            ->where('t.business_id', $business)->whereIn('t.location_id', $locations)->where('t.type', 'sell')->where('t.status', 'final')
            ->whereIn('t.id', $ids)->where('sl.variation_id', $variation)->whereNull('sl.parent_sell_line_id')
            ->select('sl.id', 'sl.transaction_id', 'sl.unit_price_before_discount', 'sl.unit_price_inc_tax')->orderBy('sl.id')->get()->groupBy('transaction_id');

        return collect($rows)->map(fn ($row) => array_merge($row, ['sale_prices' => $lines->get($row['transaction_id'], collect())->map(fn ($line) => [
            'id' => $line->id, 'unit_price' => $line->unit_price_before_discount, 'unit_price_inc_tax' => $line->unit_price_inc_tax,
        ])->all()]));
    }

    public function update(int $business, int $variation, int $transaction, array $locations, $user, array $data, string $ip): void
    {
        DB::transaction(function () use ($business, $variation, $transaction, $locations, $user, $data, $ip) {
            [$sale, $lines] = $this->context($business, $variation, $transaction, $locations, $user, true);
            abort_unless(hash_equals($this->version($sale, $lines), $data['cost_version']), 409, 'The sale has changed. Reopen the editor before saving.');
            $prices = collect($data['prices'])->keyBy('id');
            $ids = array_map('intval', $prices->keys()->all()); sort($ids);
            abort_unless($ids === $lines->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(), 422, 'The sale lines do not match this SKU.');
            $delta = 0;
            foreach ($lines as $line) {
                $price = round((float) $prices[$line->id]['unit_price'], 4);
                $discount = (float) $line->line_discount_amount;
                $base = $line->line_discount_type === 'percentage' ? $price * (1 - $discount / 100)
                    : ($line->line_discount_type === 'fixed' ? $price - $discount : $price);
                abort_if($base < 0 || $discount < 0 || ($line->line_discount_type === 'percentage' && $discount > 100), 422, 'Unit price must cover the existing line discount.');
                $rate = (float) $line->unit_price > 0 ? max(0, (float) $line->item_tax / (float) $line->unit_price) : 0;
                if ((float) $line->unit_price <= 0 && !empty($line->tax_id)) {
                    $rate = (float) DB::table('tax_rates')->where('business_id', $business)->where('id', $line->tax_id)->value('amount') / 100;
                }
                $base = round($base, 4); $tax = round($base * $rate, 4); $inclusive = round($base + $tax, 4);
                $delta += (float) $line->quantity * ($inclusive - (float) $line->unit_price_inc_tax);
                DB::table('transaction_sell_lines')->where('id', $line->id)->update([
                    'unit_price_before_discount' => $price, 'unit_price' => $base, 'item_tax' => $tax,
                    'unit_price_inc_tax' => $inclusive, 'updated_at' => now(),
                ]);
            }
            $oldSubtotal = (float) $sale->total_before_tax;
            $subtotal = round($oldSubtotal + $delta, 4);
            $discountFor = fn ($amount) => $sale->discount_type === 'percentage' ? $amount * (float) $sale->discount_amount / 100 : (float) $sale->discount_amount;
            $oldDiscount = $discountFor($oldSubtotal); $discount = $discountFor($subtotal);
            $reward = (float) ($sale->rp_redeemed_amount ?? 0);
            abort_if($subtotal - $discount - $reward < -.00001, 422, 'Unit price must cover the existing invoice discounts.');
            $oldTaxable = $oldSubtotal - $oldDiscount - $reward;
            $rate = $oldTaxable > 0 ? (float) $sale->tax_amount / $oldTaxable : 0;
            if ($oldTaxable <= 0 && !empty($sale->tax_id)) {
                $rate = (float) DB::table('tax_rates')->where('business_id', $business)->where('id', $sale->tax_id)->value('amount') / 100;
            }
            $tax = round(($subtotal - $discount - $reward) * $rate, 4);
            $packingDelta = in_array($sale->packing_charge_type ?? '', ['percent', 'percentage'], true) ? $delta * (float) $sale->packing_charge / 100 : 0;
            $unrounded = (float) $sale->final_total - (float) ($sale->round_off_amount ?? 0) + $delta - ($discount - $oldDiscount) + ($tax - (float) $sale->tax_amount) + $packingDelta;
            $settings = DB::table('business')->where('id', $business)->value('pos_settings');
            $settings = is_string($settings) ? json_decode($settings, true) : (array) $settings;
            $multiple = (float) ($settings['amount_rounding_method'] ?? 0);
            $final = round($multiple > 0 ? round($unrounded / $multiple, 0, PHP_ROUND_HALF_UP) * $multiple : $unrounded, 4);
            abort_if($final < 0, 422, 'The invoice total cannot be negative.');
            $paid = (float) DB::table('transaction_payments')->where('transaction_id', $transaction)
                ->selectRaw('COALESCE(SUM(CASE WHEN is_return = 0 THEN amount ELSE -amount END), 0) as paid')->value('paid');
            $updates = ['total_before_tax' => $subtotal, 'tax_amount' => $tax, 'final_total' => $final,
                'round_off_amount' => round($final - $unrounded, 4), 'payment_status' => $final <= $paid ? 'paid' : ($paid > 0 ? 'partial' : 'due'), 'updated_at' => now()];
            DB::table('transactions')->where('id', $transaction)->update($updates);
            SmartStockActionLog::create(['user_id' => $user->id, 'user_name' => $user->username, 'business_id' => $business, 'location_id' => $sale->location_id,
                'module_name' => 'SmartStockInventory', 'table_name' => 'transactions', 'record_id' => $transaction,
                'action_type' => 'update_sku_sale_unit_price', 'reference_type' => 'transactions', 'reference_id' => $transaction,
                'old_data' => json_encode(['sale' => $sale, 'lines' => $lines]), 'new_data' => json_encode(['sale' => $updates, 'prices' => $data['prices']]),
                'reason' => 'SKU history unit price update', 'ip_address' => $ip]);
            SellCreatedOrModified::dispatch(Transaction::findOrFail($transaction));
        });
    }
}
