<?php

namespace Modules\LoanManagement\Entities;

class LoanAbaPaywayTransaction extends BaseLoanModel
{
    protected $table = 'loan_aba_payway_transactions';

    protected $guarded = ['id'];
}
