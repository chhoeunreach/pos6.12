<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->indexIfMissing('contacts', 'contacts_business_mobile_index', ['business_id', 'mobile']);
        $this->indexIfMissing('contacts', 'contacts_business_contact_id_index', ['business_id', 'contact_id']);
        $this->indexIfMissing('products', 'products_business_sku_index', ['business_id', 'sku']);
        $this->indexIfMissing('variations', 'variations_sub_sku_index', ['sub_sku']);
        $this->indexIfMissing('purchase_lines', 'purchase_lines_lot_number_index', ['lot_number']);
        $this->indexIfMissing('variation_location_details', 'vld_location_variation_index', ['location_id', 'variation_id']);
    }

    public function down(): void
    {
        //
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
};
