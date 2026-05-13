<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanCustomerFollowup extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_customer_followups';

    protected $guarded = ['id'];
}

