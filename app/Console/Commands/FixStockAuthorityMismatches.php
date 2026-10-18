<?php

namespace App\Console\Commands;

use App\Business;
use App\Exceptions\PurchaseSellMismatch;
use App\Services\AuthorityForceRepairService;
use App\Services\StockAuthorityReconciliationService;
use App\User;
use App\Utils\Util;
use Illuminate\Console\Command;

class FixStockAuthorityMismatches extends Command
{
    protected $signature = 'stock:authority-fix-mismatches {business_id}
        {--user= : Business administrator or superadmin ID}
        {--reason= : Repair reason, 10 to 500 characters}
        {--repair-receiving : Reconstruct transfer receiving lots from verified source allocations before reconciling balances}
        {--apply : Apply an existing preview instead of generating one}
        {--token= : Token from the reviewed preview}';

    protected $description = 'Preview full Authority repairs; apply only a reviewed, unchanged proposal';

    public function handle(StockAuthorityReconciliationService $service, Util $util, AuthorityForceRepairService $receiving): int
    {
        $businessId = (int) $this->argument('business_id');
        $user = User::find((int) $this->option('user'));
        if (! Business::where('id', $businessId)->exists() || ! $user
            || ! ($user->can('superadmin') || ((int) $user->business_id === $businessId && $util->is_admin($user, $businessId)))) {
            $this->error('Select an existing business and provide its administrator using --user.');
            return self::FAILURE;
        }
        try {
            $reason = (string) $this->option('reason');
            $repairReceiving = (bool) $this->option('repair-receiving');
            if ($this->option('apply')) {
                $result = $repairReceiving
                    ? $receiving->apply($businessId, $user->id, $reason, (string) $this->option('token'))
                    : $service->reconcileAll($businessId, $reason, $user->id, null, (string) $this->option('token'));
                if ($repairReceiving) {
                    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                }
                $this->info($result['message']);
                $this->line('Audit log: #'.$result['audit_log_id']);
                if ($repairReceiving && ! $result['is_fully_verified']) {
                    $this->warn('Repair is incomplete. Review remaining_receiving_mismatches and reconciliation.unresolved above.');
                    return self::FAILURE;
                }
            } else {
                $plan = $repairReceiving
                    ? $receiving->preview($businessId, $user->id, $reason)
                    : $service->previewAll($businessId, $user->id, $reason);
                $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $this->info('Preview only. No stock records changed. Review the exact changes before using --apply --token.');
            }
            return self::SUCCESS;
        } catch (PurchaseSellMismatch $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
