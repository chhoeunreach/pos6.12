<?php

namespace Modules\DailyFinancialManagement\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialApproval extends Model
{
    protected $table = 'financial_approvals';

    public $timestamps = false;

    protected $fillable = [
        'financial_transaction_id',
        'action',
        'actor_id',
        'actor_role',
        'comments',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

