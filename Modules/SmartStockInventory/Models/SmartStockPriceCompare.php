<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartStockPriceCompare extends Model
{
    use SoftDeletes;

    protected $table = 'smart_stock_price_compares';

    protected $fillable = [
        'business_id',
        'location_id',
        'ref_no',
        'title',
        'compare_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'location_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SmartStockPriceCompareLine::class, 'price_compare_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
