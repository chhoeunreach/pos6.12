<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->indexIfMissing('transactions', 'upos_stock_report_txn_lookup', ['location_id', 'type', 'status', 'id']);
        $this->indexIfMissing('transaction_sell_lines', 'upos_stock_report_tsl_lookup', ['variation_id', 'transaction_id']);
        $this->indexIfMissing('purchase_lines', 'upos_stock_report_pl_lookup', ['variation_id', 'transaction_id']);
        $this->indexIfMissing('stock_adjustment_lines', 'upos_stock_report_sal_lookup', ['variation_id', 'transaction_id']);
        $this->indexIfMissing('variation_location_details', 'upos_vld_variation_location', ['variation_id', 'location_id']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('transactions', 'upos_stock_report_txn_lookup');
        $this->dropIndexIfExists('transaction_sell_lines', 'upos_stock_report_tsl_lookup');
        $this->dropIndexIfExists('purchase_lines', 'upos_stock_report_pl_lookup');
        $this->dropIndexIfExists('stock_adjustment_lines', 'upos_stock_report_sal_lookup');
        $this->dropIndexIfExists('variation_location_details', 'upos_vld_variation_location');
    }

    protected function indexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => strtolower((string) $row->Key_name) === strtolower($index));

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => strtolower((string) $row->Key_name) === strtolower($index));

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index) {
            $table->dropIndex($index);
        });
    }
};
