<?php

namespace Modules\LoanManagement\Entities;

class LoanPaymentSchedule extends BaseLoanModel
{
    protected $table = 'loan_payment_schedules';

    protected $guarded = ['id'];
}
