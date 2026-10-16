<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLine;
use App\TransactionSellLinesPurchaseLines;
use Illuminate\Support\Facades\DB;

class PurchaseStockGuard
{
    public static function assertUnused(PurchaseLine $line): void
    {
        if ($line->quantity_used > 0.0000001
            || TransactionSellLinesPurchaseLines::where('purchase_line_id', $line->id)->exists()
            || TransactionSellLine::where('lot_no_line_id', $line->id)->exists()) {
            throw new PurchaseSellMismatch('Purchase line #'.$line->id.' is used or referenced by stock movements. Reverse or reconcile those movements before removing this receipt. Its deducted serial cannot be reassigned automatically.');
        }
    }

    public static function assertCanUnreceive(Transaction $purchase): void
    {
        foreach ($purchase->purchase_lines()->orderBy('id')->lockForUpdate()->get() as $line) {
            self::assertUnused($line);
        }
    }

    public static function assertReceiptDate(Transaction $purchase, string $date): void
    {
        $ids = $purchase->purchase_lines()->pluck('id');
        $earlierTransfer = DB::table('transaction_sell_lines as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->where('t.business_id', $purchase->business_id)->where('t.type', 'sell_transfer')
            ->whereIn('t.status', ['final', 'completed'])->where('t.transaction_date', '<', $date)
            ->where(function ($query) use ($ids) {
                $query->whereIn('s.lot_no_line_id', $ids)->orWhereIn('s.id',
                    DB::table('transaction_sell_lines_purchase_lines')->whereIn('purchase_line_id', $ids)->select('sell_line_id'));
            })->exists();
        if ($earlierTransfer) {
            throw new PurchaseSellMismatch('The receipt cannot be dated after a completed transfer that uses its stock. Reconcile the delivery dates first.');
        }
    }
}
