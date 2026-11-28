<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPaymentSubmission extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql_loan';
    protected $table = 'loan_payment_submissions';

    protected $fillable = [
        'business_id',
        'location_id',
        'customer_id',
        'loan_id',
        'schedule_id',
        'reference_no',
        'payment_method',
        'amount',
        'currency',
        'qr_string',
        'proof_file_id',
        'proof_file_url',
        'bank_name',
        'transaction_ref',
        'note',
        'status',
        'expires_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'pos_payment_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];
}
