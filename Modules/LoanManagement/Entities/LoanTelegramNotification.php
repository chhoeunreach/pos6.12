<?php

namespace Modules\LoanManagement\Entities;

class LoanTelegramNotification extends BaseLoanModel
{
    protected $table = 'loan_telegram_notifications';

    protected $guarded = ['id'];
}
