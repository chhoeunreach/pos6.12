<?php

namespace Modules\LoanManagement\Entities;

class LoanImportBatch extends BaseLoanModel
{
    protected $table = 'loan_import_batches';

    protected $guarded = ['id'];
}
