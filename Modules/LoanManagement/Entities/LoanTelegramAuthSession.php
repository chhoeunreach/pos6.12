<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class LoanTelegramAuthSession extends Model
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_telegram_auth_sessions';

    protected $fillable = [
        'token',
        'status',
        'telegram_user_id',
        'telegram_username',
        'telegram_name',
        'phone',
        'customer_id',
        'is_new_customer',
        'api_token',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'is_new_customer' => 'boolean',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
