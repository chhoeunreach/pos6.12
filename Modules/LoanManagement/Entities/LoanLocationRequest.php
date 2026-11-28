<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanLocationRequest extends Model
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_location_requests';

    protected $fillable = [
        'business_id',
        'location_id',
        'customer_id',
        'requested_by_staff_id',
        'loan_id',
        'thread_id',
        'mode',
        'duration_minutes',
        'reason',
        'status',
        'expires_at',
        'responded_at',
        'stopped_at',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function points(): HasMany
    {
        return $this->hasMany(LoanLocationPoint::class, 'request_id');
    }
}
