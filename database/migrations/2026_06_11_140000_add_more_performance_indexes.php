<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const INDEXES = [
        'transactions' => [
            'upos_perf_txn_return_parent' => ['return_parent_id', 'type'],
            'upos_perf_txn_payment_status' => ['business_id', 'type', 'payment_status', 'transaction_date'],
            'upos_perf_txn_source' => ['business_id', 'type', 'source'],
        ],
        'transaction_payments' => [
            'upos_perf_tp_paid_on' => ['payment_for', 'paid_on'],
            'upos_perf_tp_advance' => ['payment_for', 'is_advance', 'method'],
        ],
        'transaction_sell_lines' => [
            'upos_perf_tsl_parent' => ['parent_sell_line_id'],
            'upos_perf_tsl_tax' => ['tax_id'],
        ],
        'purchase_lines' => [
            'upos_perf_pl_product_variation' => ['product_id', 'variation_id'],
            'upos_perf_pl_lot' => ['lot_number'],
        ],
        'variation_location_details' => [
            'upos_perf_vld_qty' => ['product_id', 'variation_id', 'qty_available'],
        ],
        'business_locations' => [
            'upos_perf_bl_business' => ['business_id'],
        ],
    ];

    public function up(): void
    {
        foreach (static::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $index => $columns) {
                $this->createIndexIfMissing($table, $index, $columns);
            }
        }
    }

    public function down(): void
    {
        foreach (static::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (array_keys($indexes) as $index) {
                $this->dropIndexIfExists($table, $index);
            }
        }
    }

    protected function createIndexIfMissing(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index) {
            $table->dropIndex($index);
        });
    }

    protected function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => strtolower((string) $row->Key_name) === strtolower($index));
    }
};
