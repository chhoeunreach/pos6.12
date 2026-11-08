<?php

namespace App\Services;

class LotHistoryPrices
{
    public const COLUMNS = [
        'purchase_price' => ['purchase', 'opening_stock', 'production_purchase'],
        'sell_price' => ['sell', 'production_sell'],
        'transfer_price' => ['sell_transfer', 'purchase_transfer'],
        'return_price' => ['sell_return', 'purchase_return'],
        'adjustment_price' => ['stock_adjustment'],
    ];
}
