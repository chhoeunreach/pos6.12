<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_items')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_items', function (Blueprint $table) {
            $this->string($table, 'color', 'serial_number');
            $this->string($table, 'color_snapshot', 'sku_snapshot');
            $this->string($table, 'storage', 'color');
            $this->string($table, 'storage_snapshot', 'color_snapshot');
            $this->string($table, 'serial_number_snapshot', 'imei_snapshot');
            $this->string($table, 'product_photo_path', 'storage');
            $this->longText($table, 'product_ocr_raw_text', 'product_photo_path');
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_items')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_items', function (Blueprint $table) {
            foreach ([
                'product_ocr_raw_text',
                'product_photo_path',
                'storage_snapshot',
                'storage',
                'color_snapshot',
                'color',
                'serial_number_snapshot',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('loan_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function string(Blueprint $table, string $column, string $after): void
    {
        if (Schema::connection($this->connection)->hasColumn('loan_items', $column)) {
            return;
        }

        $definition = $table->string($column)->nullable();
        if (Schema::connection($this->connection)->hasColumn('loan_items', $after)) {
            $definition->after($after);
        }
    }

    private function longText(Blueprint $table, string $column, string $after): void
    {
        if (Schema::connection($this->connection)->hasColumn('loan_items', $column)) {
            return;
        }

        $definition = $table->longText($column)->nullable();
        if (Schema::connection($this->connection)->hasColumn('loan_items', $after)) {
            $definition->after($after);
        }
    }
};
