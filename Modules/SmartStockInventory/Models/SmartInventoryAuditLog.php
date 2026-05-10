<?php

namespace Modules\SmartStockInventory\Models;

use Illuminate\Database\Eloquent\Model;

class SmartInventoryAuditLog extends Model
{
    protected $table = 'smart_inventory_audit_logs';
    protected $guarded = ['id'];
}