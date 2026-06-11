<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const INDEXES = [
        'transactions' => [
            'upos_perf_txn_biz_type_date' => ['business_id', 'type', 'transaction_date'],
            'upos_perf_txn_contact' => ['contact_id'],
            'upos_perf_txn_location_status' => ['location_id', 'status', 'transaction_date'],
            'upos_perf_txn_created_by' => ['created_by', 'transaction_date'],
        ],
        'transaction_sell_lines' => [
            'upos_perf_tsl_transaction' => ['transaction_id'],
            'upos_perf_tsl_product' => ['product_id', 'variation_id'],
        ],
        'transaction_payments' => [
            'upos_perf_tp_transaction' => ['transaction_id'],
            'upos_perf_tp_contact' => ['payment_for'],
        ],
        'variations' => [
            'upos_perf_var_product' => ['product_id'],
        ],
        'purchase_lines' => [
            'upos_perf_pl_transaction' => ['transaction_id'],
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
