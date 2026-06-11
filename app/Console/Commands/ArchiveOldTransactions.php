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
        {--force : Skip confirmation prompt}';

    protected $description = 'Archive old transactions and related records to JSON files to reduce database size';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $cutoff = Carbon::now()->subMonths($months);

        $this->info("Looking for transactions older than {$cutoff->toDateString()} ({$months} months)");

        if (! $force && ! $this->confirm("This will archive records older than {$cutoff->toDateString()}. Continue?")) {
            $this->info('Cancelled.');
            return 0;
        }

        $tables = [
            'transactions' => Transaction::class,
            'transaction_sell_lines' => TransactionSellLine::class,
            'purchase_lines' => PurchaseLine::class,
            'transaction_payments' => null,
        ];

        $archiveDir = storage_path('app/archives');
        if (! File::exists($archiveDir)) {
            File::makeDirectory($archiveDir, 0755, true);
        }

        $batchFile = $archiveDir . '/archive_' . now()->format('Ymd_His') . '.json';
        $batchData = [];
        $totalArchived = 0;

        DB::beginTransaction();

        try {
            // 1. Find old transactions
            $oldTransactions = Transaction::where('transaction_date', '<', $cutoff)
                ->whereNotIn('type', ['opening_stock', 'opening_balance'])
                ->get();

            if ($oldTransactions->isEmpty()) {
                $this->info('No old transactions found to archive.');
                DB::rollBack();
                return 0;
            }

            $ids = $oldTransactions->pluck('id')->toArray();
            $this->info("Found {$oldTransactions->count()} transactions to archive");

            // 2. Collect related data
            $batchData['transactions'] = $oldTransactions->toArray();
            $totalArchived += count($batchData['transactions']);

            $sellLines = TransactionSellLine::whereIn('transaction_id', $ids)->get();
            $batchData['transaction_sell_lines'] = $sellLines->toArray();
            $totalArchived += count($batchData['transaction_sell_lines']);

            $purchaseLines = PurchaseLine::whereIn('transaction_id', $ids)->get();
            $batchData['purchase_lines'] = $purchaseLines->toArray();
            $totalArchived += count($batchData['purchase_lines']);

            if (Schema::hasTable('transaction_payments')) {
                $payments = DB::table('transaction_payments')->whereIn('transaction_id', $ids)->get();
                $batchData['transaction_payments'] = $payments->toArray();
                $totalArchived += count($batchData['transaction_payments']);
            }

            $batchData['archived_at'] = now()->toDateTimeString();
            $batchData['cutoff_date'] = $cutoff->toDateString();

            // 3. Write archive file
            File::put($batchFile, json_encode($batchData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($dryRun) {
                $this->warn("[DRY RUN] Would archive {$totalArchived} records across " . count($batchData) . " tables");
                $this->warn("[DRY RUN] Archive file: {$batchFile}");
                DB::rollBack();
                return 0;
            }

            // 4. Delete related records first (FK constraints)
            if (! empty($batchData['transaction_payments'])) {
                $paymentIds = array_column($batchData['transaction_payments'], 'id');
                DB::table('transaction_payments')->whereIn('id', $paymentIds)->delete();
                $this->info("Deleted " . count($paymentIds) . " transaction_payments");
            }

            if (! empty($batchData['transaction_sell_lines'])) {
                TransactionSellLine::whereIn('transaction_id', $ids)->delete();
                $this->info("Deleted " . count($batchData['transaction_sell_lines']) . " transaction_sell_lines");
            }

            if (! empty($batchData['purchase_lines'])) {
                PurchaseLine::whereIn('transaction_id', $ids)->delete();
                $this->info("Deleted " . count($batchData['purchase_lines']) . " purchase_lines");
            }

            Transaction::whereIn('id', $ids)->delete();
            $this->info("Deleted " . count($batchData['transactions']) . " transactions");

            DB::commit();

            $this->info("Archived {$totalArchived} records to {$batchFile}");
            Log::info('Archive completed', [
                'file' => $batchFile,
                'total_records' => $totalArchived,
                'transaction_ids' => $ids,
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
