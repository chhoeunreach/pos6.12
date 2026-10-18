<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs destination receiving lots of stock transfers whose quantity does not
 * match the quantity sent by the source transfer.
 *
 * This targets the "Receiving lot quantity mismatch. Sent: X; Received: Y."
 * warning shown on the stock history screen. A destination purchase line often
 * carries extra units (usually on a blank/unexpected lot line) which inflates
 * the received quantity and the location balance.
 *
 * Default behaviour is a read-only dry run. Pass --apply to write the changes.
 * Applying is transactional, per source transfer, and writes an audit row to
 * smart_stock_action_logs. The command is idempotent: once corrected, a re-run
 * reports no mismatch.
 */
class RepairTransferReceipts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:repair-transfer-receipts
        {--apply : Apply the corrections (default is a dry-run)}
        {--transfer= : Process only this source transfer id or ref_no}
        {--business= : Process only transfers belonging to this business id}
        {--limit= : Stop after this many source transfers}
        {--force : Skip the confirmation prompt when applying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct destination receiving lots whose quantity does not match the source transfer quantity.';

    /**
     * Receipt statuses that count as a completed receiving document.
     *
     * @var array
     */
    protected $receiptStatuses = ['received', 'final', 'completed'];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! app()->runningUnitTests()) {
            ini_set('memory_limit', '512M');
        }

        $apply = (bool) $this->option('apply');

        $sells = $this->sourceTransfers();

        if ($sells->isEmpty()) {
            $this->info('No completed stock transfers with a receiving document were found.');

            return 0;
        }

        $plans = [];
        $skipped = [];

        foreach ($sells as $sell) {
            $receipts = DB::table('transactions')
                ->where('transfer_parent_id', $sell->id)
                ->where('type', 'purchase_transfer')
                ->whereIn('status', $this->receiptStatuses)
                ->select('id', 'location_id', 'ref_no')
                ->get();

            if ($receipts->isEmpty()) {
                continue;
            }

            $receiptIds = $receipts->pluck('id')->all();
            $destLocation = (int) $receipts->first()->location_id;

            $sent = DB::table('transaction_sell_lines')
                ->where('transaction_id', $sell->id)
                ->select('variation_id', DB::raw('SUM(quantity) as qty'))
                ->groupBy('variation_id')
                ->pluck('qty', 'variation_id');

            $received = DB::table('purchase_lines')
                ->whereIn('transaction_id', $receiptIds)
                ->select('variation_id', DB::raw('SUM(quantity) as qty'))
                ->groupBy('variation_id')
                ->pluck('qty', 'variation_id');

            $variations = $sent->keys()->merge($received->keys())->unique();

            foreach ($variations as $variationId) {
                $expected = (float) ($sent[$variationId] ?? 0);
                $got = (float) ($received[$variationId] ?? 0);

                if (abs($got - $expected) < 0.0001) {
                    continue;
                }

                $excess = $got - $expected;

                $base = [
                    'sell_id' => (int) $sell->id,
                    'sell_ref' => $sell->ref_no,
                    'business_id' => (int) $sell->business_id,
                    'variation_id' => (int) $variationId,
                    'dest_location' => $destLocation,
                    'expected' => $expected,
                    'received' => $got,
                    'excess' => $excess,
                ];

                if ($excess < 0) {
                    $base['status'] = 'MANUAL: destination received less than sent (missing receiving lots)';
                    $skipped[] = $base;

                    continue;
                }

                $plan = $this->buildPlan($base, $receiptIds, $variationId, $destLocation, $excess);

                if ($plan['status'] === 'FIXABLE') {
                    $plans[] = $plan;
                } else {
                    $skipped[] = $plan;
                }
            }
        }

        $this->render($plans, $skipped, $apply);

        if (empty($plans)) {
            return 0;
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Dry run only. Re-run with --apply to write the '.count($plans).' correction(s).');

            return 0;
        }

        if (! $this->option('force') && ! $this->confirm('Apply '.count($plans).' correction(s) now?', false)) {
            $this->warn('Aborted. No changes were made.');

            return 1;
        }

        $applied = 0;

        foreach ($plans as $plan) {
            if ($this->applyPlan($plan)) {
                $applied++;
            }
        }

        $this->newLine();
        $this->info("Applied {$applied} correction(s).");

        return 0;
    }

    /**
     * Load the source (sell_transfer) transactions to inspect.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function sourceTransfers()
    {
        return DB::table('transactions as sell')
            ->where('sell.type', 'sell_transfer')
            ->whereIn('sell.status', ['final', 'completed'])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('transactions as pur')
                    ->whereColumn('pur.transfer_parent_id', 'sell.id')
                    ->where('pur.type', 'purchase_transfer')
                    ->whereIn('pur.status', $this->receiptStatuses);
            })
            ->when($this->option('transfer'), function ($query, $transfer) {
                if (is_numeric($transfer)) {
                    $query->where('sell.id', (int) $transfer);
                } else {
                    $query->where('sell.ref_no', $transfer);
                }
            })
            ->when($this->option('business'), function ($query, $business) {
                $query->where('sell.business_id', (int) $business);
            })
            ->orderBy('sell.id')
            ->when($this->option('limit'), function ($query, $limit) {
                $query->limit((int) $limit);
            })
            ->select('sell.id', 'sell.ref_no', 'sell.business_id')
            ->get();
    }

    /**
     * Work out which receiving lines to reduce for a single over-received variation.
     *
     * @param  array  $base
     * @param  array  $receiptIds
     * @param  int  $variationId
     * @param  int  $destLocation
     * @param  float  $excess
     * @return array
     */
    protected function buildPlan(array $base, array $receiptIds, int $variationId, int $destLocation, float $excess): array
    {
        $lines = DB::table('purchase_lines')
            ->whereIn('transaction_id', $receiptIds)
            ->where('variation_id', $variationId)
            ->select('id', 'quantity', 'quantity_sold', 'quantity_returned', 'quantity_adjusted', 'mfg_quantity_used', 'lot_number')
            ->get();

        // Never reduce a line below the quantity that is actually consumed: either
        // the maintained quantity_sold, or the live total held by the sell mapping
        // table (whichever is greater). This protects downstream sales/transfers.
        $mapped = DB::table('transaction_sell_lines_purchase_lines')
            ->whereIn('purchase_line_id', $lines->pluck('id'))
            ->selectRaw('purchase_line_id, SUM(COALESCE(quantity, 0)) - SUM(COALESCE(qty_returned, 0)) as mapped_qty')
            ->groupBy('purchase_line_id')
            ->get()
            ->keyBy('purchase_line_id');

        $spare = [];

        foreach ($lines as $line) {
            $consumed = max((float) $line->quantity_sold, (float) ($mapped[$line->id]->mapped_qty ?? 0));

            $spare[$line->id] = round(
                (float) $line->quantity
                - $consumed
                - (float) ($line->quantity_returned ?? 0)
                - (float) ($line->quantity_adjusted ?? 0)
                - (float) ($line->mfg_quantity_used ?? 0),
                4
            );
        }

        $ordered = $lines->all();
        usort($ordered, function ($a, $b) use ($spare) {
            $aBlank = empty($a->lot_number) ? 0 : 1;
            $bBlank = empty($b->lot_number) ? 0 : 1;

            if ($aBlank !== $bBlank) {
                return $aBlank <=> $bBlank;
            }

            return $spare[$b->id] <=> $spare[$a->id];
        });

        $remaining = $excess;
        $reductions = [];

        foreach ($ordered as $line) {
            if ($remaining <= 0.00005) {
                break;
            }

            if ($spare[$line->id] <= 0.00005) {
                continue;
            }

            $take = min($spare[$line->id], $remaining);
            $take = round($take, 4);
            $reductions[] = [
                'line_id' => (int) $line->id,
                'lot_number' => $line->lot_number,
                'from' => (float) $line->quantity,
                'to' => round((float) $line->quantity - $take, 4),
                'take' => $take,
            ];
            $remaining = round($remaining - $take, 4);
        }

        $totalReduction = round(array_sum(array_column($reductions, 'take')), 4);

        $vld = DB::table('variation_location_details')
            ->where('variation_id', $variationId)
            ->where('location_id', $destLocation)
            ->select('id', 'qty_available')
            ->first();

        $plan = $base;
        $plan['reductions'] = $reductions;
        $plan['vld_before'] = $vld ? (float) $vld->qty_available : null;
        $plan['vld_after'] = $vld ? round((float) $vld->qty_available - $totalReduction, 4) : null;

        if ($totalReduction < $excess - 0.00005) {
            $plan['status'] = 'MANUAL: excess ('.number_format($excess, 4, '.', '').') exceeds available spare ('.number_format($totalReduction, 4, '.', '').')';

            return $plan;
        }

        if (! $vld) {
            $plan['status'] = 'MANUAL: no location balance row to adjust';

            return $plan;
        }

        if ($plan['vld_after'] < -0.00005) {
            $plan['status'] = 'MANUAL: location balance would go negative (repair balance first)';

            return $plan;
        }

        $plan['status'] = 'FIXABLE';

        return $plan;
    }

    /**
     * Print the summary tables.
     *
     * @param  array  $plans
     * @param  array  $skipped
     * @param  bool  $apply
     * @return void
     */
    protected function render(array $plans, array $skipped, bool $apply): void
    {
        $this->info('Stock transfer receiving-lot mismatch scan'.($apply ? ' (APPLY)' : ' (dry run)'));

        $rows = [];

        foreach ($plans as $plan) {
            $fs = $this->formatReductions($plan['reductions']);
            $vld = $plan['vld_before'] === null
                ? 'n/a'
                : number_format($plan['vld_before'], 4, '.', '').' -> '.number_format($plan['vld_after'], 4, '.', '');

            $rows[] = [
                $plan['sell_ref'],
                $plan['variation_id'],
                number_format($plan['expected'], 4, '.', ''),
                number_format($plan['received'], 4, '.', ''),
                number_format($plan['excess'], 4, '.', ''),
                $fs,
                $vld,
                'FIXABLE',
            ];
        }

        foreach ($skipped as $plan) {
            $rows[] = [
                $plan['sell_ref'],
                $plan['variation_id'],
                number_format($plan['expected'], 4, '.', ''),
                number_format($plan['received'], 4, '.', ''),
                number_format($plan['excess'], 4, '.', ''),
                '-',
                '-',
                $plan['status'],
            ];
        }

        if (empty($rows)) {
            $this->newLine();
            $this->info('No receiving-lot mismatches found. Nothing to do.');

            return;
        }

        $this->table(
            ['Transfer', 'Variation', 'Sent', 'Received', 'Excess', 'Reductions (line: from->to)', 'Location balance', 'Status'],
            $rows
        );

        if (! empty($skipped)) {
            $this->newLine();
            $this->warn(count($skipped).' discrepancies skipped. This command only reduces unused excess receiving quantity.');
            $this->line('--apply and --force do not repair MANUAL rows.');
            $this->line('Preview receiving reconstruction from verified source allocations with Stock Authority:');
            foreach (array_unique(array_column($skipped, 'business_id')) as $businessId) {
                $this->line('php artisan stock:authority-fix-mismatches '.(int) $businessId.' --user=ADMIN_ID --repair-receiving --reason="Restore verified transfer receiving lots"');
            }
            $this->line('Replace ADMIN_ID with an authorized administrator user ID. Review this business-wide preview, then repeat the same command with --apply --token=TOP_LEVEL_TOKEN.');
            $this->line('Already-used excess quantity remains unresolved unless the source and downstream records support a safe correction.');
        }
    }

    /**
     * Human readable description of the line reductions.
     *
     * @param  array  $reductions
     * @return string
     */
    protected function formatReductions(array $reductions): string
    {
        $parts = [];

        foreach ($reductions as $reduction) {
            $rows[] = null;
            $lot = $reduction['lot_number'] === '' || $reduction['lot_number'] === null ? '(blank lot)' : $reduction['lot_number'];
            $parts[] = sprintf(
                '%d %s: %s -> %s',
                $reduction['line_id'],
                $lot,
                number_format($reduction['from'], 4, '.', ''),
                number_format($reduction['to'], 4, '.', '')
            );
        }

        return implode('; ', $parts);
    }

    /**
     * Apply a single correction inside a transaction and write the audit row.
     *
     * @param  array  $plan
     * @return bool
     */
    protected function applyPlan(array $plan): bool
    {
        try {
            DB::transaction(function () use ($plan) {
                $oldLines = [];

                foreach ($plan['reductions'] as $reduction) {
                    $line = DB::table('purchase_lines')->where('id', $reduction['line_id'])->first();
                    if (! $line) {
                        throw new \RuntimeException('purchase line '.$reduction['line_id'].' no longer exists');
                    }

                    $oldLines[] = (array) $line;

                    DB::table('purchase_lines')
                        ->where('id', $reduction['line_id'])
                        ->update([
                            'quantity' => $reduction['to'],
                            'updated_at' => now(),
                        ]);
                }

                DB::table('variation_location_details')
                    ->where('variation_id', $plan['variation_id'])
                    ->where('location_id', $plan['dest_location'])
                    ->update([
                        'qty_available' => $plan['vld_after'],
                        'updated_at' => now(),
                    ]);

                DB::table('smart_stock_action_logs')->insert([
                    'user_id' => null,
                    'user_name' => 'artisan:stock:repair-transfer-receipts',
                    'business_id' => $plan['business_id'],
                    'location_id' => $plan['dest_location'],
                    'action_type' => 'transfer_receiving_lot_correction',
                    'reference_type' => 'sell_transfer',
                    'reference_id' => $plan['sell_id'],
                    'old_data' => json_encode([
                        'purchase_lines' => $oldLines,
                        'vld_qty_available' => $plan['vld_before'],
                    ], JSON_UNESCAPED_UNICODE),
                    'new_data' => json_encode([
                        'reductions' => $plan['reductions'],
                        'vld_qty_available' => $plan['vld_after'],
                    ], JSON_UNESCAPED_UNICODE),
                    'reason' => sprintf(
                        'Correct receiving lot quantity for %s (variation %d): received %s was %s above sent %s.',
                        $plan['sell_ref'],
                        $plan['variation_id'],
                        number_format($plan['received'], 4, '.', ''),
                        number_format($plan['excess'], 4, '.', ''),
                        number_format($plan['expected'], 4, '.', '')
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $this->line('  fixed '.$plan['sell_ref'].' variation '.$plan['variation_id']);

            return true;
        } catch (\Throwable $e) {
            $this->error('  FAILED '.$plan['sell_ref'].' variation '.$plan['variation_id'].': '.$e->getMessage());

            return false;
        }
    }
}
