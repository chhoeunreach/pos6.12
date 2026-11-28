<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class LoanNotification extends Model
{
    protected $connection = 'mysql_loan';
    protected $table = 'loan_notifications';

    protected $fillable = [
        'business_id',
        'location_id',
        'recipient_type',
        'recipient_id',
        'category',
        'title',
        'body',
        'data',
        'is_read',
        'read_at',
        'pushed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'pushed_at' => 'datetime',
    ];
}
