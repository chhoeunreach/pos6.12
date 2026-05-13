<?php

namespace Modules\LoanManagement\Entities;

class LoanImportRow extends BaseLoanModel
{
    protected $table = 'loan_import_rows';

    protected $guarded = ['id'];
}
