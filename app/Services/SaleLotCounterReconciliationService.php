<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/** Repairs a cached sold counter only when the original allocations prove its value. */
class SaleLotCounterReconciliationService
{
    public const POLICY_VERSION = 'pos-overselling-20261015-2';

    public function repairForAllocation(array $business, $line): ?array
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Sale counter reconciliation requires an active allocation transaction.');
        }
        $settings = $business['pos_settings'] ?? [];
        $settings = is_array($settings) ? $settings : (json_decode((string) $settings, true) ?: []);
        if (filter_var($settings['allow_overselling'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || \App\Utils\BusinessUtil::allowsOverselling((int) $business['id'])) {
            // The allocator retains the selected receipt and records any shortage. Do not rewrite disputed history.
            return null;
        }
        $util = app(TransactionUtil::class);
        $movement = Transaction::where('id', $line->transaction_id)->where('business_id', $business['id'])
            ->where('location_id', $business['location_id'])->where('type', 'sell')->where('status', 'final')->first();
        if (!$movement || empty($line->lot_no_line_id)) {
            return null;
        }
        $lot = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('p.id', $line->lot_no_line_id)->where('t.business_id', $business['id'])->where('t.location_id', $business['location_id'])
            ->where('t.status', 'received')->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
            ->where('p.product_id', $line->product_id)->where('p.variation_id', $line->variation_id)->lockForUpdate()
            ->select('p.*', 't.business_id')->selectRaw($util->lotQuantitySoldSql('p', true, (int) $line->id).' as other_history_sold')->first();
        if (!$lot) {
            return null;
        }
        $related = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->where(function ($query) use ($lot) {
                $query->where('s.lot_no_line_id', $lot->id)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')
                    ->where('purchase_line_id', $lot->id)->select('sell_line_id'));
            })->orderBy('s.id')->lockForUpdate()
            ->get(['s.*', 't.business_id', 't.location_id', 't.type', 't.status', 't.transaction_date', 't.invoice_no', 't.ref_no']);
        $maps = DB::table('transaction_sell_lines_purchase_lines as m')
            ->leftJoin('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')->leftJoin('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where(function ($query) use ($related, $lot) {
                $query->whereIn('m.sell_line_id', $related->pluck('id'))->orWhere('m.purchase_line_id', $lot->id);
            })->orderBy('m.id')->lockForUpdate()->get(['m.*', 'p.product_id', 'p.variation_id', 't.business_id', 't.location_id',
                't.type as receipt_type', 't.status as receipt_status', 't.transaction_date as receipt_date']);
        foreach ($related as $saleLine) {
            $mapped = $maps->where('sell_line_id', $saleLine->id);
            if ((int) $saleLine->business_id !== (int) $business['id'] || (int) $saleLine->location_id !== (int) $business['location_id']
                || $saleLine->status !== 'final' || !in_array($saleLine->type, ['sell', 'sell_transfer'], true)
                || (int) $saleLine->product_id !== (int) $line->product_id || (int) $saleLine->variation_id !== (int) $line->variation_id) {
                throw $this->allocationConflict($lot);
            }
            $current = (int) $saleLine->id === (int) $line->id;
            if ($current) {
                // An edit may need its missing mapping, or only an increased quantity, allocated next.
                if ((float) $saleLine->quantity_returned !== 0.0 || (float) $mapped->sum('qty_returned') > 0
                    || (float) $mapped->sum('quantity') > (float) $saleLine->quantity + 0.0000001) {
                    throw $this->allocationConflict($lot);
                }
            } elseif ($mapped->isEmpty() || abs((float) $mapped->sum('quantity') - (float) $saleLine->quantity) > 0.0000001
                || abs((float) $mapped->sum('qty_returned') - (float) $saleLine->quantity_returned) > 0.0000001) {
                throw $this->allocationConflict($lot);
            }
            foreach ($mapped as $mapping) {
                if ((int) $mapping->business_id !== (int) $business['id'] || (int) $mapping->location_id !== (int) $business['location_id']
                    || $mapping->receipt_status !== 'received' || !in_array($mapping->receipt_type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                    || (int) $mapping->product_id !== (int) $line->product_id || (int) $mapping->variation_id !== (int) $line->variation_id
                    || (float) $mapping->quantity < 0 || (float) $mapping->qty_returned < 0 || (float) $mapping->qty_returned > (float) $mapping->quantity
                    || ($saleLine->type === 'sell_transfer' && \Carbon::parse($mapping->receipt_date)->gt(\Carbon::parse($saleLine->transaction_date)))) {
                    throw $this->allocationConflict($lot);
                }
                if (!empty($saleLine->lot_no_line_id) && (int) $mapping->purchase_line_id !== (int) $saleLine->lot_no_line_id) {
                    // Only expose document details after both sides pass business/location checks.
                    throw $this->allocationConflict($lot, $saleLine, $mapping);
                }
            }
        }
        if (!$related->contains('id', $line->id)
            || $maps->where('purchase_line_id', $lot->id)->contains(fn ($row) => !$related->contains('id', $row->sell_line_id))) {
            throw $this->allocationConflict($lot);
        }
        $ownMapped = $maps->where('sell_line_id', $line->id)->where('purchase_line_id', $lot->id)->sum(fn ($row) => $row->quantity - $row->qty_returned);
        $verified = (float) $lot->other_history_sold + (float) $ownMapped;
        $actual = (float) $maps->where('purchase_line_id', $lot->id)->sum(fn ($row) => $row->quantity - $row->qty_returned);
        $available = (float) $lot->quantity - (float) $lot->quantity_returned - (float) $lot->quantity_adjusted - (float) $lot->mfg_quantity_used - $verified;
        if ($verified < 0 || abs($verified - $actual) > 0.0000001) {
            throw $this->allocationConflict($lot);
        }
        if ($available + 0.0000001 < (float) $line->quantity) {
            throw new PurchaseSellMismatch('Lot #'.$lot->id.' does not have enough stock after its existing allocations are accounted for.', [
                'purchase_line_id' => (int) $lot->id, 'serial' => $lot->lot_number,
                'requested_quantity' => (float) $line->quantity, 'history_available' => $available,
                'system_available' => max(0.0, $available),
                'reason' => 'Existing allocations already consume this stock. Choose the actual available receipt or reconcile the conflicting movement.',
            ]);
        }
        if (abs((float) $lot->quantity_sold - $verified) < 0.0000001) {
            return null;
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            $tables = collect(['transactions', 'purchase_lines', 'transaction_sell_lines', 'transaction_sell_lines_purchase_lines',
                config('activitylog.table_name', 'activity_log')])->map(fn ($name) => DB::connection()->getTablePrefix().$name)->unique();
            $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->whereIn('TABLE_NAME', $tables)->get(['TABLE_NAME', 'ENGINE']);
            if ($engines->count() !== $tables->count() || $engines->contains(fn ($row) => strtolower((string) $row->ENGINE) !== 'innodb')) {
                return null;
            }
        }
        DB::table('purchase_lines')->where('id', $lot->id)->update(['quantity_sold' => $verified, 'updated_at' => now()]);

        return ['purchase_line_id' => (int) $lot->id, 'serial' => $lot->lot_number, 'sell_line_id' => (int) $line->id,
            'pending_allocation_quantity' => (float) $line->quantity, 'existing_mapped_quantity' => (float) $ownMapped,
            'quantity_sold_before' => (float) $lot->quantity_sold, 'quantity_sold_after' => $verified];
    }

    private function allocationConflict($lot, $saleLine = null, $mapping = null): PurchaseSellMismatch
    {
        $details = [
            'purchase_line_id' => (int) $lot->id, 'serial' => $lot->lot_number,
            'business_id' => (int) $lot->business_id, 'overselling_enabled' => false, 'stock_policy_version' => self::POLICY_VERSION,
            'reason' => 'A related allocation is missing, inconsistent, or belongs to another business or location. The sold counter cannot be repaired automatically.',
        ];
        if ($saleLine && $mapping) {
            $reference = $saleLine->ref_no ?: ($saleLine->invoice_no ?: '#'.$saleLine->transaction_id);
            $reason = ($saleLine->type === 'sell_transfer' ? 'Transfer ' : 'Invoice ').$reference.', line #'.$saleLine->id
                .' selects receipt #'.$saleLine->lot_no_line_id.' but deducts receipt #'.$mapping->purchase_line_id
                .'. Correct this allocation in Authority before saving.';
            $details += ['kind' => 'selected_receipt_mismatch', 'transaction_id' => (int) $saleLine->transaction_id,
                'reference' => $reference, 'sell_line_id' => (int) $saleLine->id,
                'selected_purchase_line_id' => (int) $saleLine->lot_no_line_id,
                'deducted_purchase_line_id' => (int) $mapping->purchase_line_id,
                'allocation_id' => (int) $mapping->id, 'related_allocations' => [$reason]];
            $details['reason'] = $reason;
            if (auth()->user()?->can('stock_inventory.authority')) {
                $details['links'] = [['label' => 'Review stock corrections', 'url' => ssi_route('ssi.authority.index')]];
            }
            if (auth()->user()?->can('business_settings.access')) {
                $details['links'][] = ['label' => 'Business settings: allow overselling',
                    'url' => action([\App\Http\Controllers\BusinessController::class, 'getBusinessSettings'])];
            }
        }

        return new PurchaseSellMismatch('Lot #'.$lot->id.' has unverified stock allocations. Reconcile those allocations before updating this sale.', $details);
    }
}
