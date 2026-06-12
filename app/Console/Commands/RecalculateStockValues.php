<?php

namespace App\Console\Commands;

use App\Business;
use App\BusinessLocation;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateStockValues extends Command
{
    protected $signature = 'pos:recalculate-stock-values
        {--business= : Only process a specific business ID}
        {--date= : Snapshot date (defaults to today)}
        {--force : Recalculate even if today\'s snapshot already exists}';

    protected $description = 'Pre-compute stock values and cache them for fast report loading';

    public function handle(TransactionUtil $transactionUtil): int
    {
        $targetDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dateStr = $targetDate->format('Y-m-d');
        $force = (bool) $this->option('force');

        $businesses = Business::query();
        if ($businessId = $this->option('business')) {
            $businesses->where('id', $businessId);
        }

        $count = 0;
        $bar = $this->output->createProgressBar($businesses->count());
        $bar->start();

        foreach ($businesses->cursor() as $business) {
            $locations = BusinessLocation::where('business_id', $business->id)->get();

            foreach ($locations as $location) {
                $snapshotExists = DB::table('stock_value_snapshots')
                    ->where('business_id', $business->id)
                    ->where('location_id', $location->id)
                    ->where('snapshot_date', $dateStr)
                    ->exists();

                if ($snapshotExists && !$force) {
                    continue;
                }

                $cacheKey = 'report_stock_value_'.$business->id.'_'.$location->id.'_'.md5(serialize([]));

                $data = Cache::remember($cacheKey, 86400, function () use ($transactionUtil, $business, $dateStr, $location) {
                    $permitted = 'all';

                    $byPurchasePrice = $transactionUtil->getOpeningClosingStock(
                        $business->id, $dateStr, $location->id, false, false, [], $permitted
                    );

                    $bySalePrice = $transactionUtil->getOpeningClosingStock(
                        $business->id, $dateStr, $location->id, false, true, [], $permitted
                    );

                    return [
                        'closing_stock_by_pp' => $byPurchasePrice ?? 0,
                        'closing_stock_by_sp' => $bySalePrice ?? 0,
                    ];
                });

                DB::table('stock_value_snapshots')->updateOrInsert(
                    [
                        'business_id' => $business->id,
                        'location_id' => $location->id,
                        'snapshot_date' => $dateStr,
                    ],
                    [
                        'stock_value_by_purchase_price' => $data['closing_stock_by_pp'] ?? 0,
                        'stock_value_by_sale_price' => $data['closing_stock_by_sp'] ?? 0,
                        'calculated_at' => now(),
                    ]
                );

                $count++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Recalculated stock values for {$count} location(s) on {$dateStr}");
        Log::info('Stock values recalculated', ['date' => $dateStr, 'locations' => $count]);

        return 0;
    }
}
