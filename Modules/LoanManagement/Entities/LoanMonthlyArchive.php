<?php

namespace Modules\LoanManagement\Entities;

class LoanMonthlyArchive extends BaseLoanModel
{
    protected $table = 'loan_monthly_archives';

    protected $guarded = ['id'];
}
