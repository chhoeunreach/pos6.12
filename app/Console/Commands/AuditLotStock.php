<?php

namespace App\Console\Commands;

use App\Business;
use App\Services\LotStockAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditLotStock extends Command
{
    protected $signature = 'stock:audit-lots {business_id} {--invoice= : Limit the report to one invoice}';

    protected $description = 'Audit selected invoice lots, stock allocations and lot balances without changing records';

    public function handle(LotStockAuditService $audit): int
    {
        $businessId = (int) $this->argument('business_id');
        if ($businessId <= 0 || ! Business::where('id', $businessId)->exists()) {
            $this->error('Select an existing business ID.');

            return self::FAILURE;
        }
        $report = $audit->audit($businessId, $this->option('invoice') ?: null);
        $directory = storage_path('app/stock-audits');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/lot-stock-'.$businessId.'-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->info('Selected sale lines checked: '.$report['checked_selected_sale_lines']);
        $this->line('Sale lines needing reconciliation: '.count($report['sale_line_issues']));
        $this->line('Lot balances needing reconciliation: '.count($report['stock_issues']));
        $this->line('Report: '.$path);
        $this->line('No database records were changed.');

        return self::SUCCESS;
    }
}
