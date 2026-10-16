<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

class PurchaseReturnStockService
{
    public function __construct(private ProductUtil $productUtil, private TransactionUtil $transactionUtil)
    {
    }

    /** Quantities are cumulative returns in base units, keyed by original receipt line ID. */
    public function apply(Transaction $purchase, array $quantities): float
    {
        return DB::transaction(function () use ($purchase, $quantities) {
            $purchase = Transaction::where('business_id', $purchase->business_id)->where('type', 'purchase')
                ->lockForUpdate()->findOrFail($purchase->id);
            if ($purchase->status !== 'received') {
                throw new PurchaseSellMismatch('Only received purchases can be returned.');
            }
            $lines = $purchase->purchase_lines()->with('product.unit')->orderBy('id')->lockForUpdate()->get();
            if (array_diff(array_map('intval', array_keys($quantities)), $lines->modelKeys())) {
                throw new PurchaseSellMismatch('A return line does not belong to this purchase.');
            }

            $total = 0.0;
            foreach ($lines as $line) {
                $quantity = (float) ($quantities[$line->id] ?? 0);
                if (! is_finite($quantity) || $quantity < 0) {
                    throw new PurchaseSellMismatch('Return quantities must be nonnegative.');
                }
                if ($line->product?->unit && ! $line->product->unit->allow_decimal
                    && abs($quantity - round($quantity)) > 0.0000001) {
                    throw new PurchaseSellMismatch('This product requires a whole-unit return quantity.');
                }
                $oldQuantity = (float) $line->quantity_returned;
                $maximum = (float) $line->quantity - (float) $line->quantity_sold
                    - (float) $line->quantity_adjusted - (float) $line->mfg_quantity_used;
                if ($quantity > $oldQuantity) {
                    $ledgerAvailable = (float) DB::table('purchase_lines')->where('id', $line->id)
                        ->selectRaw($this->transactionUtil->lotQuantityAvailableSql('purchase_lines').' as available')
                        ->value('available');
                    $maximum = min($maximum, $ledgerAvailable + $oldQuantity);
                }
                // An unchanged legacy shortage must not prevent editing a different line.
                if ($quantity > $oldQuantity && $quantity > $maximum + 0.0000001) {
                    throw new PurchaseSellMismatch('Return quantity exceeds the available stock for purchase line #'.$line->id.'.');
                }
                if ($quantity !== $oldQuantity) {
                    $this->productUtil->decreaseProductQuantity($line->product_id, $line->variation_id,
                        $purchase->location_id, $quantity, $oldQuantity);
                    $line->update(['quantity_returned' => $quantity]);
                }
                $total += (float) $line->purchase_price_inc_tax * $quantity;
            }

            return $total;
        });
    }
}
