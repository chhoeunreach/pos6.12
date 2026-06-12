<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const INDEXES = [
        'upos_perf_txn_home_sales' => ['business_id', 'type', 'status', 'transaction_date', 'location_id'],
        'upos_perf_txn_return_type_parent' => ['type', 'return_parent_id'],
    ];

    public function up(): void
    {
        foreach (static::INDEXES as $index => $columns) {
            if (! $this->indexExists($index)) {
                Schema::table('transactions', function (Blueprint $table) use ($columns, $index) {
                    $table->index($columns, $index);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(static::INDEXES) as $index) {
            if ($this->indexExists($index)) {
                Schema::table('transactions', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }
    }

    protected function indexExists(string $index): bool
    {
        return collect(DB::select('SHOW INDEX FROM `transactions`'))
            ->contains(fn ($row) => strtolower((string) $row->Key_name) === strtolower($index));
    }
};
