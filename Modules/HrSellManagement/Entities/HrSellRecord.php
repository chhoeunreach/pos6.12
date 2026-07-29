<?php

namespace Modules\HrSellManagement\Entities;

use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrSellRecord extends Model
{
    use SoftDeletes;

    protected $table = 'hr_sell_records';
    protected $guarded = ['id'];
    protected $casts = [
        'follow_up_date' => 'date',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function hrUser()
    {
        return $this->belongsTo(User::class, 'hr_user_id');
    }

    public function notes()
    {
        return $this->hasMany(HrSellNote::class, 'hr_sell_record_id');
    }

    public function approvals()
    {
        return $this->hasMany(HrSellApproval::class, 'hr_sell_record_id');
    }
}
