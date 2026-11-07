<?php

namespace App\Services;

class PurchaseLotCost
{
    /** SQL fragments supplied by the report queries, never by request input. */
    public static function receiptValue(string $lotAlias, string $business, string $variation, string $column = 'purchase_price_inc_tax'): string
    {
        // Transfers may carry an old cost. Resolve the originating purchase by
        // exact lot and variation, including receipts at another business branch.
        return "(SELECT receipt_line.$column FROM purchase_lines as receipt_line
            INNER JOIN transactions as receipt_tx ON receipt_tx.id = receipt_line.transaction_id
            WHERE $lotAlias.variation_id = $variation
                AND NULLIF(TRIM($lotAlias.lot_number), '') IS NOT NULL
                AND receipt_line.lot_number = $lotAlias.lot_number
                AND receipt_line.variation_id = $variation
                AND receipt_tx.business_id = $business
                AND receipt_tx.type = 'purchase' AND receipt_tx.status = 'received'
                AND receipt_line.purchase_price_inc_tax IS NOT NULL
            ORDER BY receipt_tx.transaction_date DESC, receipt_tx.id DESC, receipt_line.id DESC LIMIT 1)";
    }
}
