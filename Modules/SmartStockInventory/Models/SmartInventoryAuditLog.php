<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmartInventoryAuditLog extends Model
{
    use SoftDeletes;

    protected $table = 'smart_inventory_audit_logs';
    protected $guarded = ['id'];
}
