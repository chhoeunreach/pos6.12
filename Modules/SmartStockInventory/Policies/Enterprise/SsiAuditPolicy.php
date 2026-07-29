<?php

namespace Modules\SmartStockInventory\Policies\Enterprise;

use App\User;
use Modules\SmartStockInventory\Models\Enterprise\SsiAudit;

class SsiAuditPolicy
{
    public function view(User $user, SsiAudit $audit): bool
    {
        return (int) $user->business_id === (int) $audit->business_id && $user->can('ssi.audit.view');
    }

    public function update(User $user, SsiAudit $audit): bool
    {
        return (int) $user->business_id === (int) $audit->business_id && $user->can('ssi.audit.update');
    }

    public function scan(User $user, SsiAudit $audit): bool
    {
        return (int) $user->business_id === (int) $audit->business_id && $user->can('ssi.audit.scan');
    }

    public function approve(User $user, SsiAudit $audit): bool
    {
        return (int) $user->business_id === (int) $audit->business_id && $user->can('ssi.audit.approve');
    }
}
