<?php

namespace Modules\LoanManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class BaseLoanModel extends Model
{
    protected $connection = 'mysql_loan';
}
