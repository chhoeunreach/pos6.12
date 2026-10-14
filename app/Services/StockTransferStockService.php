<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\BusinessLocation;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLine;
use App\TransactionSellLinesPurchaseLines;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\DB;

class StockTransferStockService
{
    public function __construct(private TransactionUtil $transactionUtil, private ProductUtil $productUtil)
    {
    }

    public function apply(Transaction $sell, Transaction $purchase, string $accountingMethod, bool $repairVerifiedCounters = false): void
    {
        DB::transaction(function () use ($sell, $purchase, $accountingMethod, $repairVerifiedCounters) {
            [$sell, $purchase] = $this->lockPair($sell, $purchase);
            $allowOverselling = \App\Utils\BusinessUtil::allowsOverselling((int) $sell->business_id);
            $lines = $sell->sell_lines()->with('product')->orderBy('id')->get();
            if (TransactionSellLinesPurchaseLines::whereIn('sell_line_id', $lines->pluck('id'))->exists()) {
                throw new PurchaseSellMismatch('This transfer already has stock allocations. Reconcile it before completing it again.');
            }
            $this->assertDestinationUnused($purchase);
            if ($repairVerifiedCounters) {
                $this->repairVerifiedSourceCounters($sell, $lines, $allowOverselling);
            }
            $sell->update(['status' => 'final']);
            $purchase->update(['status' => 'received']);
            $this->transactionUtil->mapPurchaseSell([
                'id' => $sell->business_id, 'location_id' => $sell->location_id,
                'accounting_method' => $accountingMethod, 'pos_settings' => ['allow_overselling' => $allowOverselling],
            ], $lines, 'purchase');

            // Build destination batches from the actual source allocations, including FIFO transfers.
            $destinationLines = [];
            foreach ($lines as $line) {
                if ((float) $line->quantity <= 0 || (int) $line->product->business_id !== (int) $sell->business_id) {
                    throw new PurchaseSellMismatch('Transfer products must belong to this business and have positive quantities.');
                }
                $mappings = $line->sell_line_purchase_lines()->with('purchase_line')->get();
                $batches = $line->product->enable_stock ? $mappings : collect([null]);
                foreach ($batches as $mapping) {
                    $source = $mapping?->purchase_line;
                    $destinationLines[] = [
                        'product_id' => $line->product_id, 'variation_id' => $line->variation_id,
                        'quantity' => $mapping ? $mapping->quantity : $line->quantity,
                        'purchase_price' => $line->unit_price,
                        'purchase_price_inc_tax' => $line->unit_price_inc_tax,
                        'item_tax' => 0, 'tax_id' => null, 'sub_unit_id' => $line->sub_unit_id,
                        'lot_number' => $source?->lot_number, 'mfg_date' => $source?->mfg_date, 'exp_date' => $source?->exp_date,
                    ];
                }
            }
            $purchase->purchase_lines()->delete();
            $purchase->purchase_lines()->createMany($destinationLines);
            foreach ($lines as $line) {
                if ($line->product->enable_stock) {
                    $this->moveQuantity($line, $sell->location_id, $purchase->location_id, (float) $line->quantity, $allowOverselling);
                }
            }
            $this->productUtil->adjustStockOverSelling($purchase->load('purchase_lines.product'));
        });
    }

    private function repairVerifiedSourceCounters(Transaction $sell, $lines, bool $allowOverselling): void
    {
        $ids = $lines->pluck('lot_no_line_id')->filter()->unique()->sort()->values();
        if ($ids->isEmpty()) {
            return;
        }
        $lots = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->whereIn('p.id', $ids)->where('t.business_id', $sell->business_id)->where('t.location_id', $sell->location_id)
            ->where('t.status', 'received')->where('t.transaction_date', '<=', $sell->transaction_date)
            ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
            ->orderBy('p.id')->lockForUpdate()->select('p.*')
            ->selectRaw($this->transactionUtil->lotQuantitySoldSql('p', true).' as history_sold')->get();
        $repairs = [];
        foreach ($lots as $lot) {
            $historySold = (float) $lot->history_sold;
            if (abs((float) $lot->quantity_sold - $historySold) <= 0.0000001 || $historySold < 0) {
                continue;
            }
            if ($lines->where('lot_no_line_id', $lot->id)->contains(fn ($line) => (int) $line->product_id !== (int) $lot->product_id
                || (int) $line->variation_id !== (int) $lot->variation_id)) {
                continue;
            }
            // A counter can be rebuilt only when selected-lot history and actual allocations agree.
            $related = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
                ->where(function ($q) use ($lot) {
                    $q->where('s.lot_no_line_id', $lot->id)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')
                        ->where('purchase_line_id', $lot->id)->select('sell_line_id'));
                })->get(['s.*', 't.business_id', 't.location_id', 't.status', 't.type', 't.transaction_date']);
            $mappings = DB::table('transaction_sell_lines_purchase_lines as m')
                ->leftJoin('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')
                ->leftJoin('transactions as t', 't.id', '=', 'p.transaction_id')->whereIn('m.sell_line_id', $related->pluck('id'))
                ->get(['m.*', 'p.product_id', 'p.variation_id', 't.business_id', 't.location_id', 't.status', 't.transaction_date']);
            $verified = !DB::table('transaction_sell_lines_purchase_lines as m')->leftJoin('transaction_sell_lines as s', 's.id', '=', 'm.sell_line_id')
                ->where('m.purchase_line_id', $lot->id)->whereNull('s.id')->exists();
            foreach ($related as $line) {
                $mapped = $mappings->where('sell_line_id', $line->id);
                if ($mapped->isEmpty() && $line->status !== 'final') {
                    continue;
                }
                if ((int) $line->business_id !== (int) $sell->business_id || $line->status !== 'final'
                    || !in_array($line->type, ['sell', 'sell_transfer'], true)
                    || abs((float) $line->quantity - (float) $mapped->sum('quantity')) > 0.0000001
                    || abs((float) $line->quantity_returned - (float) $mapped->sum('qty_returned')) > 0.0000001) {
                    $verified = false;
                    break;
                }
                foreach ($mapped as $mapping) {
                    if ((int) $mapping->business_id !== (int) $sell->business_id || (int) $mapping->location_id !== (int) $line->location_id
                        || (int) $mapping->product_id !== (int) $line->product_id || (int) $mapping->variation_id !== (int) $line->variation_id
                        || $mapping->status !== 'received' || (float) $mapping->quantity < 0 || (float) $mapping->qty_returned < 0
                        || (float) $mapping->qty_returned > (float) $mapping->quantity
                        || (!empty($line->lot_no_line_id) && (int) $mapping->purchase_line_id !== (int) $line->lot_no_line_id)
                        || ($line->type === 'sell_transfer' && $mapping->transaction_date > $line->transaction_date)) {
                        $verified = false;
                        break 2;
                    }
                }
            }
            $allocated = (float) $mappings->where('purchase_line_id', $lot->id)->sum(fn ($m) => (float) $m->quantity - (float) $m->qty_returned);
            $available = (float) $lot->quantity - (float) $lot->quantity_returned - (float) $lot->quantity_adjusted - (float) $lot->mfg_quantity_used - $historySold;
            if (!$verified || abs($allocated - $historySold) > 0.0000001 || (!$allowOverselling && $available < -0.0000001)) {
                continue;
            }
            DB::table('purchase_lines')->where('id', $lot->id)->update(['quantity_sold' => $historySold]);
            $repairs[] = ['purchase_line_id' => (int) $lot->id, 'serial' => $lot->lot_number,
                'quantity_sold_before' => (float) $lot->quantity_sold, 'quantity_sold_after' => $historySold];
        }
        if ($repairs) {
            $this->transactionUtil->activityLog($sell, 'stock_reconciled', null, [
                'mode' => 'verified_counters_before_completion', 'counter_repairs' => $repairs,
            ]);
        }
    }

    public function reverse(Transaction $sell, Transaction $purchase): void
    {
        DB::transaction(function () use ($sell, $purchase) {
            [$sell, $purchase] = $this->lockPair($sell, $purchase);
            $this->assertDestinationUnused($purchase);
            if (! in_array($sell->status, ['final', 'completed'], true)) {
                if (TransactionSellLinesPurchaseLines::whereIn('sell_line_id', $sell->sell_lines()->pluck('id'))->exists()) {
                    throw new PurchaseSellMismatch('An incomplete transfer already has stock allocations. Reconcile it before changing or deleting it.');
                }
                return;
            }
            $lines = $sell->sell_lines()->with('product')->orderBy('id')->get();
            foreach ($lines as $line) {
                if (! $line->product->enable_stock) {
                    continue;
                }
                $mappings = $line->sell_line_purchase_lines()->lockForUpdate()->get();
                if ((float) $line->quantity_returned > 0 || (float) $mappings->sum('qty_returned') > 0) {
                    throw new PurchaseSellMismatch('This transfer has return movements. Reconcile them before changing or deleting it.');
                }
                if (abs((float) $mappings->sum('quantity') - (float) $line->quantity) > 0.0000001) {
                    throw new PurchaseSellMismatch('Transfer stock allocations are incomplete. Reconcile this transfer before changing or deleting it.');
                }
                foreach ($mappings as $mapping) {
                    if (empty($mapping->purchase_line_id) && empty($line->lot_no_line_id)) {
                        // Reverse a recorded unallocated oversold quantity without inventing a source receipt.
                        $mapping->delete();
                        continue;
                    }
                    if (! empty($line->lot_no_line_id) && (int) $mapping->purchase_line_id !== (int) $line->lot_no_line_id) {
                        throw new PurchaseSellMismatch('Transfer allocation differs from its selected lot. Reconcile this transfer first.');
                    }
                    $source = PurchaseLine::lockForUpdate()->find($mapping->purchase_line_id);
                    $quantity = (float) $mapping->quantity - (float) $mapping->qty_returned;
                    if (! $source || (float) $source->quantity_sold + 0.0000001 < $quantity) {
                        throw new PurchaseSellMismatch('Source lot balance is inconsistent. Reconcile this transfer first.');
                    }
                    $source->decrement('quantity_sold', $quantity);
                    $mapping->delete();
                }
                $this->moveQuantity($line, $purchase->location_id, $sell->location_id, (float) $line->quantity);
            }
            $sell->update(['status' => 'pending']);
            $purchase->update(['status' => 'pending']);
        });
    }

    public function assertDestinationUnused(Transaction $purchase): void
    {
        $sql = $this->transactionUtil->lotQuantityAvailableSql('purchase_lines');
        $lines = $purchase->purchase_lines()->lockForUpdate()
            ->select('purchase_lines.*')->selectRaw($sql.' as ledger_available')->get();
        if (TransactionSellLine::whereIn('lot_no_line_id', $lines->pluck('id'))->exists()
            || TransactionSellLinesPurchaseLines::whereIn('purchase_line_id', $lines->pluck('id'))->exists()) {
            throw new PurchaseSellMismatch('Destination lots are referenced by other stock movements. Reconcile those movements before changing or deleting this transfer.');
        }
        foreach ($lines as $line) {
            if ($line->quantity_used > 0.0000001 || (float) $line->ledger_available + 0.0000001 < (float) $line->quantity) {
                throw new PurchaseSellMismatch('Transferred stock has been sold, transferred, returned or adjusted. Reverse those movements before changing or deleting this transfer.');
            }
        }
    }

    private function lockPair(Transaction $sell, Transaction $purchase): array
    {
        $sell = Transaction::where('id', $sell->id)->lockForUpdate()->firstOrFail();
        $purchase = Transaction::where('id', $purchase->id)->lockForUpdate()->firstOrFail();
        if ($sell->type !== 'sell_transfer' || $purchase->type !== 'purchase_transfer'
            || (int) $sell->business_id !== (int) $purchase->business_id
            || (int) $purchase->transfer_parent_id !== (int) $sell->id
            || (int) $sell->location_id === (int) $purchase->location_id
            || BusinessLocation::where('business_id', $sell->business_id)
                ->whereIn('id', [$sell->location_id, $purchase->location_id])->count() !== 2) {
            throw new PurchaseSellMismatch('Invalid source and destination transfer pair.');
        }

        return [$sell, $purchase];
    }

    private function moveQuantity($line, int $from, int $to, float $quantity, bool $allowOverselling = false): void
    {
        $variation = Variation::lockForUpdate()->findOrFail($line->variation_id);
        $locations = [$from, $to];
        sort($locations);
        $balances = [];
        foreach ($locations as $location) {
            $balances[$location] = VariationLocationDetails::firstOrCreate([
                'product_id' => $line->product_id, 'variation_id' => $line->variation_id,
                'product_variation_id' => $variation->product_variation_id, 'location_id' => $location,
            ], ['qty_available' => 0]);
            $balances[$location] = VariationLocationDetails::where('id', $balances[$location]->id)->lockForUpdate()->firstOrFail();
        }
        if (! $allowOverselling && (float) $balances[$from]->qty_available + 0.0000001 < $quantity) {
            throw new PurchaseSellMismatch('The source location does not have enough stock for this transfer. Reconcile its stock balance first.');
        }
        $balances[$from]->decrement('qty_available', $quantity);
        $balances[$to]->increment('qty_available', $quantity);
    }
}
