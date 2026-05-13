<?php

namespace Modules\LoanManagement\Entities;

class LoanItem extends BaseLoanModel
{
    protected $table = 'loan_items';

    protected $guarded = ['id'];
}
