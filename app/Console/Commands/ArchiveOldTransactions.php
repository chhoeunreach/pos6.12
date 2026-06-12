<?php

namespace App\Console\Commands;

use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ArchiveOldTransactions extends Command
{
    protected $signature = 'pos:archive-transactions
        {--months=12 : Archive transactions older than this many months}
        {--dry-run : Preview what would be archived without deleting}
        {--force : Skip confirmation prompt}
        {--chunk=500 : Number of transactions to process per batch}';

    protected $description = 'Archive old transactions and related records to JSON files to reduce database size';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $chunkSize = (int) $this->option('chunk');
        $cutoff = Carbon::now()->subMonths($months);

        $this->info("Looking for transactions older than {$cutoff->toDateString()} ({$months} months)");

        if (! $force && ! $this->confirm("This will archive records older than {$cutoff->toDateString()}. Continue?")) {
            $this->info('Cancelled.');
            return 0;
        }

        $archiveDir = storage_path('app/archives');
        if (! File::exists($archiveDir)) {
            File::makeDirectory($archiveDir, 0755, true);
        }

        $batchFile = $archiveDir . '/archive_' . now()->format('Ymd_His') . '.json';
        $allBatchData = [];
        $totalArchived = 0;

        // Count total for progress
        $totalCount = Transaction::where('transaction_date', '<', $cutoff)
            ->whereNotIn('type', ['opening_stock', 'opening_balance'])
            ->count();

        if ($totalCount === 0) {
            $this->info('No old transactions found to archive.');
            return 0;
        }

        $this->info("Found {$totalCount} transactions to archive (processing in chunks of {$chunkSize})");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        DB::beginTransaction();

        try {
            $hasMore = true;
            $lastId = 0;

            while ($hasMore) {
                $oldTransactions = Transaction::where('transaction_date', '<', $cutoff)
                    ->whereNotIn('type', ['opening_stock', 'opening_balance'])
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($chunkSize)
                    ->get();

                if ($oldTransactions->isEmpty()) {
                    $hasMore = false;
                    break;
                }

                $lastId = $oldTransactions->last()->id;
                $ids = $oldTransactions->pluck('id')->toArray();

                $chunkData = [];
                $chunkData['transactions'] = $oldTransactions->toArray();

                $sellLines = TransactionSellLine::whereIn('transaction_id', $ids)->get();
                $chunkData['transaction_sell_lines'] = $sellLines->toArray();

                $purchaseLines = PurchaseLine::whereIn('transaction_id', $ids)->get();
                $chunkData['purchase_lines'] = $purchaseLines->toArray();

                $payments = [];
                if (Schema::hasTable('transaction_payments')) {
                    $payments = DB::table('transaction_payments')->whereIn('transaction_id', $ids)->get();
                    $chunkData['transaction_payments'] = $payments->toArray();
                }

                $allBatchData[] = $chunkData;

                if (! $dryRun) {
                    $paymentIds = [];
                    if (! empty($payments)) {
                        $paymentIds = array_column($payments->toArray(), 'id');
                        DB::table('transaction_payments')->whereIn('id', $paymentIds)->delete();
                    }

                    if (! $sellLines->isEmpty()) {
                        TransactionSellLine::whereIn('transaction_id', $ids)->delete();
                    }

                    if (! $purchaseLines->isEmpty()) {
                        PurchaseLine::whereIn('transaction_id', $ids)->delete();
                    }

                    Transaction::whereIn('id', $ids)->delete();
                }

                $count = count($ids) + $sellLines->count() + $purchaseLines->count() + count($payments);
                $totalArchived += $count;
                $bar->advance(count($ids));
            }

            $bar->finish();
            $this->line('');

            $batchData = [
                'archived_at' => now()->toDateTimeString(),
                'cutoff_date' => $cutoff->toDateString(),
                'chunks' => $allBatchData,
            ];

            File::put($batchFile, json_encode($batchData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($dryRun) {
                $this->warn("[DRY RUN] Would archive {$totalArchived} records");
                $this->warn("[DRY RUN] Archive file: {$batchFile}");
                DB::rollBack();
                return 0;
            }

            DB::commit();

            $this->info("Archived {$totalArchived} records to {$batchFile}");
            Log::info('Archive completed', [
                'file' => $batchFile,
                'total_records' => $totalArchived,
            ]);

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Archive failed: ' . $e->getMessage());
            Log::error('ArchiveOldTransactions failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
