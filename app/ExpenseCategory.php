<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ExpenseCategory extends Model
{
    use SoftDeletes;
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected static function booted()
    {
        static::saving(function ($category) {
            $category->code = trim((string) $category->code);
            if ($category->code === '') {
                $category->code = null;
                return;
            }

            if (static::codeIsTaken($category->business_id, $category->code, $category->getKey())) {
                throw ValidationException::withMessages([
                    'code' => __('expense.category_code_taken', ['code' => $category->code]),
                ]);
            }
        });
    }

    public static function codeIsTaken($businessId, $code, $exceptId = null)
    {
        return static::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(code)) = ?', [mb_strtolower(trim((string) $code))])
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function sub_categories()
    {
        return $this->hasMany(\App\ExpenseCategory::class, 'parent_id');
    }

    /**
     * Scope a query to only include main categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyParent($query)
    {
        return $query->whereNull('parent_id');
    }
}
