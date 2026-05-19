<?php

namespace Modules\LoanManagement\Console;

use Illuminate\Console\Command;
use Modules\LoanManagement\Services\LoanCollectionService;

class RunCollectionAutomationCommand extends Command
{
    protected $signature = 'loan-management:collection-automation';

    protected $description = 'Apply LoanManagement collection workflow automation rules.';

    public function handle(LoanCollectionService $service): int
    {
        $result = $service->runAutomation();
        $this->info('Collection automation complete. Updated loans: '.(int) ($result['updated'] ?? 0));

        return self::SUCCESS;
    }
}
