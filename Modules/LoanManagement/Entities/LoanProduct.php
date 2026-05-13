<?php

namespace Modules\LoanManagement\Entities;

class LoanProduct extends BaseLoanModel
{
    protected $table = 'loan_products';

    protected $guarded = ['id'];
}
