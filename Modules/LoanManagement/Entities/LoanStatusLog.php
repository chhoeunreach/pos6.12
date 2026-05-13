<?php

namespace Modules\LoanManagement\Entities;

class LoanStatusLog extends BaseLoanModel
{
    protected $table = 'loan_status_logs';

    protected $guarded = ['id'];
}
