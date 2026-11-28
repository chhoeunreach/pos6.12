<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanLocationPoint extends Model
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_location_points';

    protected $fillable = [
        'request_id',
        'customer_id',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(LoanLocationRequest::class, 'request_id');
    }
}
