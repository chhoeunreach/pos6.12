<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartInventoryApproval extends Model
{
    protected $table = 'smart_inventory_approvals';
    protected $guarded = ['id'];
    protected $casts = ['approved_at' => 'datetime'];
}