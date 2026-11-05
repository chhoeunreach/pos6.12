<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\Business;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    protected $table = 'financial_categories';

    protected $fillable = [
        'business_id',
        'code',
        'name_en',
        'name_kh',
        'type',
        'parent_id',
        'is_operating',
        'is_system',
        'is_active',
        'description',
        'created_by',
    ];

    protected $casts = [
        'is_operating' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FinancialCategory::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDisplayNameAttribute(): string
    {
        $name = app()->getLocale() === 'kh' && ! empty($this->name_kh) ? $this->name_kh : $this->name_en;
        return $this->code ? "[{$this->code}] {$name}" : $name;
    }
}

