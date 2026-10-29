<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanQuotationItem extends BaseLoanModel
{
    protected $table = 'loan_quotation_items';

    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_name_snapshot',
        'sku_snapshot',
        'serial_number_snapshot',
        'description',
        'photo_path',
        'quantity',
        'unit_price',
        'discount_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(LoanQuotation::class, 'quotation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'product_id');
    }
}
