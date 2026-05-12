<?php

namespace Modules\LoanManagement\Entities;

class LoanPaymentDetail extends BaseLoanModel
{
    protected $table = 'loan_payment_details';

    protected $fillable = [
        'payment_id',
        'payment_method_id',
        'payment_method_snapshot',
        'currency',
        'amount',
        'exchange_rate',
        'amount_base',
        'reference_number',
        'note',
    ];
}
