<?php

namespace Modules\MismatchFixer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScanMismatchCommand extends Command
{
    protected $signature = 'mismatch:scan {--business_id=} {--location_id=} {--product_id=} {--variation_id=} {--sku=}';
    protected $description = 'Scan purchase lines for mismatch and fake sold problems';

    public function handle(): int
    {
        $business_id = (int)($this->option('business_id') ?: 0);
        if ($business_id <= 0) {
            $this->error('business_id is required in CLI for safety.');
            return self::FAILURE;
        }
        $query = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('t.business_id', $business_id);
        if ($this->option('location_id')) $query->where('pl.location_id', $this->option('location_id'));
        if ($this->option('product_id')) $query->where('p.id', $this->option('product_id'));
        if ($this->option('variation_id')) $query->where('pl.variation_id', $this->option('variation_id'));
        if ($this->option('sku')) $query->where(function ($q) { $q->where('p.sku', $this->option('sku'))->orWhere('v.sub_sku', $this->option('sku')); });
        $rows = $query->select([
            'pl.id',
            DB::raw('(COALESCE(pl.quantity,0)-COALESCE(pl.quantity_sold,0)-COALESCE(pl.quantity_adjusted,0)-COALESCE(pl.quantity_returned,0)) as calculated_available'),
            'pl.available_quantity',
            'pl.quantity_sold',
        ])->get();
        $mismatch = $rows->filter(fn($r) => round((float)$r->available_quantity - (float)$r->calculated_available, 4) !== 0.0)->count();
        $fakeSold = $rows->filter(function ($r) {
            if ((float)$r->quantity_sold <= 0) return false;
            return !DB::table('transaction_sell_lines_purchase_lines as tslpl')
                ->join('transaction_sell_lines as tsl', 'tsl.id', '=', 'tslpl.sell_line_id')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->where('tslpl.purchase_line_id', $r->id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->exists();
        })->count();
        $this->info('Scan complete. Mismatch rows: '.$mismatch.' | Fake sold candidates: '.$fakeSold.' | Total checked: '.$rows->count());
        return self::SUCCESS;
    }
}
