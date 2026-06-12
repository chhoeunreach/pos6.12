<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const INDEXES = [
        'transactions' => [
            'upos_stock_report_txn_business_type_status' => ['business_id', 'type', 'status', 'location_id', 'id'],
            'upos_stock_report_txn_business_type' => ['business_id', 'type', 'location_id', 'id'],
        ],
        'transaction_sell_lines' => [
            'upos_stock_report_tsl_transaction_variation' => ['transaction_id', 'variation_id'],
        ],
        'purchase_lines' => [
            'upos_stock_report_pl_transaction_variation' => ['transaction_id', 'variation_id'],
        ],
        'stock_adjustment_lines' => [
            'upos_stock_report_sal_transaction_variation' => ['transaction_id', 'variation_id'],
        ],
    ];

    public function up(): void
    {
        foreach (static::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $index => $columns) {
                if (! $this->indexExists($table, $index)) {
                    Schema::table($table, function (Blueprint $table) use ($columns, $index) {
                        $table->index($columns, $index);
                    });
                }
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
                if ($this->indexExists($table, $index)) {
                    Schema::table($table, function (Blueprint $table) use ($index) {
                        $table->dropIndex($index);
                    });
                }
            }
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => strtolower((string) $row->Key_name) === strtolower($index));
    }
};
