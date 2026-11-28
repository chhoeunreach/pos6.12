<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class LoanDeviceToken extends Model
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_device_tokens';

    protected $fillable = [
        'user_type',
        'user_id',
        'business_id',
        'device_id',
        'platform',
        'fcm_token',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];
}
