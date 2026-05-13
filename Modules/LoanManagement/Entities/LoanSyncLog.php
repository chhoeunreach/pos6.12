<?php

namespace Modules\LoanManagement\Entities;

class LoanSyncLog extends BaseLoanModel
{
    protected $table = 'loan_sync_logs';

    protected $guarded = ['id'];
}
