<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const INDEXES = [
        'variation_location_details' => [
            'upos_vld_location' => ['location_id'],
        ],
        'transaction_sell_lines_purchase_lines' => [
            'upos_tslpl_sell_line' => ['sell_line_id'],
            'upos_tslpl_purchase_line' => ['purchase_line_id'],
            'upos_tslpl_adjustment_line' => ['stock_adjustment_line_id'],
        ],
        'media' => [
            'upos_media_model' => ['model_type', 'model_id'],
        ],
        'activity_log' => [
            'upos_al_subject' => ['subject_id', 'subject_type'],
            'upos_al_causer' => ['causer_id', 'causer_type'],
            'upos_al_business' => ['business_id'],
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
