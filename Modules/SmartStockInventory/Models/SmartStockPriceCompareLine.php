<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartStockPriceCompareLine extends Model
{
    protected $table = 'smart_stock_price_compare_lines';

    protected $fillable = [
        'price_compare_id',
        'business_id',
        'product_id',
        'variation_id',
        'sku',
        'qty',
        'sort_order',
    ];

    protected $casts = [
        'price_compare_id' => 'integer',
        'business_id' => 'integer',
        'product_id' => 'integer',
        'variation_id' => 'integer',
        'qty' => 'float',
        'sort_order' => 'integer',
    ];

    public function compare(): BelongsTo
    {
        return $this->belongsTo(SmartStockPriceCompare::class, 'price_compare_id');
    }
}
