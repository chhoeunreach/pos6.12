<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Support\Facades\Storage;

class LoanProduct extends BaseLoanModel
{
    protected $table = 'loan_products';

    protected $guarded = ['id'];

    protected $casts = [
        'selling_price' => 'float',
        'cost_price' => 'float',
        'qty_available' => 'integer',
        'meta_json' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(LoanProductItem::class, 'loan_product_id');
    }

    public function location()
    {
        return $this->belongsTo(LoanBusinessLocation::class, 'loan_business_location_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $meta = is_array($this->meta_json) ? $this->meta_json : json_decode((string) $this->meta_json, true);
        $path = $meta['image_path'] ?? null;
        if (! $path) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        $clean = ltrim($path, '/');
        if (is_file(public_path($clean))) {
            return asset($clean);
        }

        return null;
    }

    public function getMinDownPaymentPercentAttribute(): float
    {
        $meta = is_array($this->meta_json) ? $this->meta_json : json_decode((string) $this->meta_json, true);
        return (float) ($meta['min_down_payment_percent'] ?? 0);
    }

    public function getCategoryAttribute(): string
    {
        $meta = is_array($this->meta_json) ? $this->meta_json : json_decode((string) $this->meta_json, true);
        return (string) ($meta['category'] ?? '');
    }

    public function getBrandAttribute(): string
    {
        $meta = is_array($this->meta_json) ? $this->meta_json : json_decode((string) $this->meta_json, true);
        return (string) ($meta['brand'] ?? '');
    }

    public function getDescriptionAttribute(): string
    {
        $meta = is_array($this->meta_json) ? $this->meta_json : json_decode((string) $this->meta_json, true);
        return (string) ($meta['description'] ?? '');
    }
}
