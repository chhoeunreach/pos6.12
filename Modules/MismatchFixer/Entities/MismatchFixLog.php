<?php

namespace Modules\MismatchFixer\Entities;

use Illuminate\Database\Eloquent\Model;

class MismatchFixLog extends Model
{
    protected $table = 'mismatch_fix_logs';

    protected $fillable = [
        'business_id', 'user_id', 'purchase_line_id', 'transaction_id', 'variation_id', 'location_id',
        'problem_type', 'old_values', 'new_values', 'reason', 'status', 'message', 'fixed_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'fixed_at' => 'datetime',
    ];
}
