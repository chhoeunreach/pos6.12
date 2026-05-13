<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanCustomerNote extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_customer_notes';

    protected $guarded = ['id'];
}

