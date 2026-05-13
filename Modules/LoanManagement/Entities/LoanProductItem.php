<?php

namespace Modules\LoanManagement\Entities;

class LoanProductItem extends BaseLoanModel
{
    protected $table = 'loan_product_items';

    protected $guarded = ['id'];
}
