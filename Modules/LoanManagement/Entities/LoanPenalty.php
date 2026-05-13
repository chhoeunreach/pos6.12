<?php

namespace Modules\LoanManagement\Entities;

class LoanPenalty extends BaseLoanModel
{
    protected $table = 'loan_penalties';

    protected $guarded = ['id'];
}
