<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanGuarantor extends BaseLoanModel
{
    use SoftDeletes;

    protected $table = 'loan_guarantors';

    protected $guarded = ['id'];
}

