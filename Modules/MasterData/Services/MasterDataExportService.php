<?php

namespace Modules\MasterData\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class MasterDataExportService
{
    private const TEMP_DIR = 'temp/master_data';

    // Keep export portable/safe: intersect with real columns at runtime
    private const USERS_SAFE_FIELDS = [
        'id',
        'surname',
        'first_name',
        'last_name',
        'username',
        'email',
        'language',
        'contact_no',
        'address',
        'business_id',
        'status',
        'is_cmmsn_agnt',
        'cmmsn_percent',
        'selected_contacts',
        'dob',
        'gender',
        'marital_status',
        'blood_group',
        'contact_number',
        'alt_number',
        'family_number',
        'fb_link',
        'twitter_link',
        'social_media_1',
        'social_media_2',
        'permanent_address',
        'current_address',
        'guardian_name',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'bank_details',
        'id_proof_name',
        'id_proof_number',
        'essentials_salary',
        'essentials_pay_period',
        'essentials_pay_cycle',
        'essentials_sales_target',
        'allow_login',
        'created_at',
        'updated_at',
    ];

    public function export(int $business_id, array $options): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is required.');
        }

        $sections = array_values(array_unique($options['sections'] ?? []));
        if (empty($sections)) {
            throw new \RuntimeException('No sections selected.');
        }

        $disk = Storage::disk('local');
        $runId = now()->format('Ymd_His') . '_' . Str::random(8);
        $baseDir = self::TEMP_DIR . '/runs/' . $runId;
        $disk->makeDirectory($baseDir);

        try {
            $data = [
                'users' => [],
                'products' => [],
                'categories' => [],
                'brands' => [],
                'units' => [],
                'taxes' => [],
                'locations' => [],
                'settings' => (object) [],
            ];

            $recordCounts = [];

            foreach ($sections as $section) {
                switch ($section) {
                    case 'users':
                        $data['users'] = $this->exportUsers($business_id);
                        $recordCounts['users'] = count($data['users']);
                        break;
                    case 'categories':
                        $data['categories'] = $this->exportCategories($business_id);
                        $recordCounts['categories'] = count($data['categories']);
                        break;
                    case 'brands':
                        $data['brands'] = $this->exportBrands($business_id);
                        $recordCounts['brands'] = count($data['brands']);
                        break;
                    case 'units':
                        $data['units'] = $this->exportUnits($business_id);
                        $recordCounts['units'] = count($data['units']);
                        break;
                    case 'taxes':
                        $data['taxes'] = $this->exportTaxes($business_id);
                        $recordCounts['taxes'] = count($data['taxes']);
                        break;
                    case 'products':
                        $data['products'] = $this->exportProducts($business_id);
                        $recordCounts['products'] = count($data['products']);
                        break;
                    case 'locations':
                        $data['locations'] = $this->exportBusinessLocations($business_id);
                        $recordCounts['locations'] = count($data['locations']);
                        break;
                    case 'settings':
                        $data['settings'] = $this->exportBusinessSettings($business_id);
                        $recordCounts['settings'] = is_array($data['settings']) ? count($data['settings']) : 1;
                        break;
                }
            }

            $manifest = [
                'module' => 'MasterData',
                'created_at' => now()->format('Y-m-d H:i:s'),
                'business_id' => $business_id,
                'sections' => $sections,
                'record_counts' => $recordCounts,
            ];

            $disk->put($baseDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $disk->put($baseDir . '/data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zipName = 'master_data_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';
            $zipRelative = self::TEMP_DIR . '/exports/' . $zipName;
            $disk->makeDirectory(self::TEMP_DIR . '/exports');

            $zipFull = $this->localDiskPath($disk, $zipRelative);
            $zipDir = dirname($zipFull);
            if (! is_dir($zipDir)) {
                @mkdir($zipDir, 0775, true);
            }
            $zip = new ZipArchive();
            if ($zip->open($zipFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create ZIP file.');
            }

            $manifestRel = $baseDir . '/manifest.json';
            $dataRel = $baseDir . '/data.json';
            try {
                if (! $disk->exists($manifestRel) || ! $disk->exists($dataRel)) {
                    throw new \RuntimeException('Temporary export files not found. Please check storage permissions.');
                }

                $zip->addFromString('manifest.json', (string) $disk->get($manifestRel));
                $zip->addFromString('data.json', (string) $disk->get($dataRel));
            } finally {
                if (! $zip->close()) {
                    throw new \RuntimeException('ZIP creation failed. Please check PHP temp directory permissions.');
                }
            }

            $this->assertZipIsValid($zipFull);
            return $zipFull;
        } finally {
            $disk->deleteDirectory($baseDir);
        }
    }

    public function exportSql(int $business_id, array $options, callable $writeLine): void
    {
        $sections = array_values(array_unique($options['sections'] ?? []));
        if (empty($sections)) {
            throw new \RuntimeException('No sections selected.');
        }

        $writeLine('-- UltimatePOS Master Data Export');
        $writeLine('-- Created at: ' . now()->format('Y-m-d H:i:s'));
        $writeLine('-- Business ID: ' . $business_id);
        $writeLine('-- Sections: ' . implode(', ', $sections));
        $writeLine('');
        $writeLine('SET FOREIGN_KEY_CHECKS=0;');
        $writeLine('');

        // Order matters for FK dependencies
        $order = ['business', 'users', 'categories', 'brands', 'units', 'tax_rates', 'business_locations', 'products', 'product_variations', 'variations'];
        $tables = $this->tablesForSections($sections);
        $tables = array_values(array_unique($tables));

        foreach ($order as $t) {
            if (! in_array($t, $tables, true)) {
                continue;
            }
            $this->dumpTableAsSql($t, $business_id, $writeLine);
            $writeLine('');
        }

        $writeLine('SET FOREIGN_KEY_CHECKS=1;');
        $writeLine('');
    }

    private function exportUsers(int $business_id): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $columns = Schema::getColumnListing('users');
        $allowed = array_values(array_intersect(self::USERS_SAFE_FIELDS, $columns));

        $q = DB::table('users')->select($allowed);
        if (in_array('business_id', $allowed, true)) {
            $q->where('business_id', $business_id);
        }

        $orderCol = in_array('id', $allowed, true) ? 'id' : (in_array('email', $allowed, true) ? 'email' : $allowed[0] ?? 'username');
        $rows = $q->orderBy($orderCol)->get();

        return json_decode(json_encode($rows), true) ?: [];
    }

    private function tablesForSections(array $sections): array
    {
        $tables = [];
        foreach ($sections as $s) {
            switch ($s) {
                case 'users':
                    $tables[] = 'users';
                    break;
                case 'categories':
                    $tables[] = 'categories';
                    break;
                case 'brands':
                    $tables[] = 'brands';
                    break;
                case 'units':
                    $tables[] = 'units';
                    break;
                case 'taxes':
                    $tables[] = 'tax_rates';
                    break;
                case 'locations':
                    $tables[] = 'business_locations';
                    break;
                case 'settings':
                    $tables[] = 'business';
                    break;
                case 'products':
                    $tables[] = 'products';
                    $tables[] = 'product_variations';
                    $tables[] = 'variations';
                    break;
            }
        }
        return $tables;
    }

    private function dumpTableAsSql(string $table, int $business_id, callable $writeLine): void
    {
        if (! Schema::hasTable($table)) {
            $writeLine('-- Skipped missing table: ' . $table);
            return;
        }

        $cols = Schema::getColumnListing($table);
        if (empty($cols)) {
            $writeLine('-- Skipped empty columns table: ' . $table);
            return;
        }

        $q = DB::table($table)->select($cols);

        if ($table === 'business') {
            if (in_array('id', $cols, true)) {
                $q->where('id', $business_id);
            }
        } elseif (Schema::hasColumn($table, 'business_id')) {
            $q->where('business_id', $business_id);
        } elseif ($table === 'product_variations') {
            // product_variations has product_id only
            if (Schema::hasTable('products') && Schema::hasColumn('products', 'business_id')) {
                $productIds = DB::table('products')->where('business_id', $business_id)->pluck('id')->all();
                if (! empty($productIds)) {
                    $q->whereIn('product_id', $productIds);
                } else {
                    $writeLine('-- No products found for business; skipped: ' . $table);
                    return;
                }
            }
        } elseif ($table === 'variations') {
            if (Schema::hasTable('products') && Schema::hasColumn('products', 'business_id')) {
                $productIds = DB::table('products')->where('business_id', $business_id)->pluck('id')->all();
                if (! empty($productIds)) {
                    $q->whereIn('product_id', $productIds);
                } else {
                    $writeLine('-- No products found for business; skipped: ' . $table);
                    return;
                }
            }
        }

        // Avoid dumping huge blobs in some builds (logo, featured_products) still ok; keep portable by including only existing cols
        $writeLine('-- Table: ' . $table);
        $writeLine('DELETE FROM `' . $table . '`' . $this->deleteWhereClause($table, $business_id) . ';');

        $rows = $q->orderBy(in_array('id', $cols, true) ? 'id' : $cols[0])->get();
        foreach ($rows as $rowObj) {
            $row = (array) $rowObj;
            $writeLine($this->buildInsertSql($table, $cols, $row) . ';');
        }
    }

    private function deleteWhereClause(string $table, int $business_id): string
    {
        if ($table === 'business') {
            return ' WHERE `id`=' . (int) $business_id;
        }

        if (Schema::hasColumn($table, 'business_id')) {
            return ' WHERE `business_id`=' . (int) $business_id;
        }

        // For child product tables, delete by product_id set
        if (in_array($table, ['product_variations', 'variations'], true) && Schema::hasTable('products')) {
            return ' WHERE `product_id` IN (SELECT `id` FROM `products` WHERE `business_id`=' . (int) $business_id . ')';
        }

        return '';
    }

    private function buildInsertSql(string $table, array $cols, array $row): string
    {
        $values = [];
        foreach ($cols as $c) {
            $values[] = $this->sqlValue($row[$c] ?? null);
        }

        $colList = implode(',', array_map(static fn ($c) => '`' . $c . '`', $cols));
        $valList = implode(',', $values);

        return 'INSERT INTO `' . $table . '` (' . $colList . ') VALUES (' . $valList . ')';
    }

    private function sqlValue($v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if ($v instanceof \DateTimeInterface) {
            return "'" . $v->format('Y-m-d H:i:s') . "'";
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }

        $s = (string) $v;
        $s = str_replace("\\", "\\\\", $s);
        $s = str_replace("'", "\\'", $s);
        $s = str_replace("\r", "\\r", $s);
        $s = str_replace("\n", "\\n", $s);
        $s = str_replace("\t", "\\t", $s);

        return "'" . $s . "'";
    }

    private function exportCategories(int $business_id): array
    {
        if (! Schema::hasTable('categories')) {
            return [];
        }
        $columns = Schema::getColumnListing('categories');
        $allowed = array_values(array_intersect($columns, [
            'id',
            'name',
            'business_id',
            'short_code',
            'parent_id',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $rows = DB::table('categories')
            ->select($allowed)
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->get();

        $cats = json_decode(json_encode($rows), true) ?: [];

        // Add parent name to improve portable re-mapping
        $byId = [];
        foreach ($cats as $c) {
            if (isset($c['id'])) {
                $byId[(int) $c['id']] = $c['name'] ?? null;
            }
        }
        foreach ($cats as &$c) {
            $pid = (int) ($c['parent_id'] ?? 0);
            $c['_parent_name'] = $pid > 0 ? ($byId[$pid] ?? null) : null;
        }
        unset($c);

        return $cats;
    }

    private function exportBrands(int $business_id): array
    {
        if (! Schema::hasTable('brands')) {
            return [];
        }
        $columns = Schema::getColumnListing('brands');
        $allowed = array_values(array_intersect($columns, [
            'id',
            'business_id',
            'name',
            'description',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $rows = DB::table('brands')
            ->select($allowed)
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->get();

        return json_decode(json_encode($rows), true) ?: [];
    }

    private function exportUnits(int $business_id): array
    {
        if (! Schema::hasTable('units')) {
            return [];
        }
        $columns = Schema::getColumnListing('units');
        $allowed = array_values(array_intersect($columns, [
            'id',
            'business_id',
            'actual_name',
            'short_name',
            'allow_decimal',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $rows = DB::table('units')
            ->select($allowed)
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->get();

        return json_decode(json_encode($rows), true) ?: [];
    }

    private function exportTaxes(int $business_id): array
    {
        if (! Schema::hasTable('tax_rates')) {
            return [];
        }
        $columns = Schema::getColumnListing('tax_rates');
        $allowed = array_values(array_intersect($columns, [
            'id',
            'business_id',
            'name',
            'amount',
            'is_tax_group',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $rows = DB::table('tax_rates')
            ->select($allowed)
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->get();

        return json_decode(json_encode($rows), true) ?: [];
    }

    private function exportProducts(int $business_id): array
    {
        if (! (Schema::hasTable('products') && Schema::hasTable('product_variations') && Schema::hasTable('variations'))) {
            return [];
        }

        $productCols = Schema::getColumnListing('products');
        $productAllowed = array_values(array_intersect($productCols, [
            'id',
            'name',
            'business_id',
            'type',
            'unit_id',
            'brand_id',
            'category_id',
            'sub_category_id',
            'tax',
            'tax_type',
            'enable_stock',
            'alert_quantity',
            'sku',
            'barcode_type',
            'created_by',
            'created_at',
            'updated_at',
        ]));

        $products = DB::table('products')
            ->select($productAllowed)
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->get();
        $products = json_decode(json_encode($products), true) ?: [];

        $productIds = array_values(array_filter(array_map(static fn ($p) => $p['id'] ?? null, $products)));
        if (empty($productIds)) {
            return [];
        }

        $pvCols = Schema::getColumnListing('product_variations');
        $pvAllowed = array_values(array_intersect($pvCols, [
            'id',
            'name',
            'product_id',
            'is_dummy',
            'created_at',
            'updated_at',
        ]));
        $pvs = DB::table('product_variations')
            ->select($pvAllowed)
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get();
        $pvs = json_decode(json_encode($pvs), true) ?: [];

        $varCols = Schema::getColumnListing('variations');
        $varAllowed = array_values(array_intersect($varCols, [
            'id',
            'name',
            'product_id',
            'sub_sku',
            'product_variation_id',
            'default_purchase_price',
            'dpp_inc_tax',
            'profit_percent',
            'default_sell_price',
            'sell_price_inc_tax',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $vars = DB::table('variations')
            ->select($varAllowed)
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get();
        $vars = json_decode(json_encode($vars), true) ?: [];

        $pvsByProduct = [];
        foreach ($pvs as $pv) {
            $pvsByProduct[(int) $pv['product_id']][] = $pv;
        }
        $varsByProduct = [];
        foreach ($vars as $v) {
            $varsByProduct[(int) $v['product_id']][] = $v;
        }

        // Add friendly relation hints for remap (names only)
        $categoryById = $this->simpleMap('categories', 'id', 'name', $business_id);
        $brandById = $this->simpleMap('brands', 'id', 'name', $business_id);
        $unitById = $this->simpleMap('units', 'id', 'short_name', $business_id);
        $taxById = $this->simpleMap('tax_rates', 'id', 'name', $business_id);

        $out = [];
        foreach ($products as $p) {
            $pid = (int) ($p['id'] ?? 0);
            $p['_category_name'] = ! empty($p['category_id']) ? ($categoryById[(int) $p['category_id']] ?? null) : null;
            $p['_brand_name'] = ! empty($p['brand_id']) ? ($brandById[(int) $p['brand_id']] ?? null) : null;
            $p['_unit_short_name'] = ! empty($p['unit_id']) ? ($unitById[(int) $p['unit_id']] ?? null) : null;
            $p['_tax_name'] = ! empty($p['tax']) ? ($taxById[(int) $p['tax']] ?? null) : null;

            $out[] = [
                'product' => $p,
                'product_variations' => $pvsByProduct[$pid] ?? [],
                'variations' => $varsByProduct[$pid] ?? [],
            ];
        }

        return $out;
    }

    private function exportBusinessSettings(int $business_id): array
    {
        if (! Schema::hasTable('business')) {
            return [];
        }

        $cols = Schema::getColumnListing('business');
        $row = (array) DB::table('business')->where('id', $business_id)->first();
        if (empty($row)) {
            return [];
        }

        // Remove keys that must never be restored as-is
        unset($row['id']);
        unset($row['owner_id']);
        unset($row['created_at']);
        unset($row['updated_at']);

        // Keep only real columns (in case of casting quirks)
        $filtered = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $cols, true)) {
                $filtered[$k] = $v;
            }
        }

        return $filtered;
    }

    private function exportBusinessLocations(int $business_id): array
    {
        if (! Schema::hasTable('business_locations')) {
            return [];
        }

        $cols = Schema::getColumnListing('business_locations');
        $safe = [
            'id',
            'business_id',
            'name',
            'landmark',
            'country',
            'state',
            'city',
            'zip_code',
            'mobile',
            'alternate_number',
            'email',
            'website',
            'invoice_scheme_id',
            'invoice_layout_id',
            'sale_invoice_layout_id',
            'selling_price_group_id',
            'print_receipt_on_invoice',
            'receipt_printer_type',
            'printer_id',
            'mobile',
            'custom_field1',
            'custom_field2',
            'custom_field3',
            'custom_field4',
            'featured_products',
            'is_active',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $allowed = array_values(array_intersect($cols, $safe));
        $q = DB::table('business_locations')->select($allowed);
        if (Schema::hasColumn('business_locations', 'business_id')) {
            $q->where('business_id', $business_id);
        }

        $orderCol = in_array('id', $allowed, true) ? 'id' : (in_array('name', $allowed, true) ? 'name' : ($allowed[0] ?? 'name'));
        $rows = $q->orderBy($orderCol)->get();

        return json_decode(json_encode($rows), true) ?: [];
    }

    private function localDiskPath($disk, string $relativePath): string
    {
        // Laravel FilesystemAdapter has path() for local disk. Keep fallback for older builds.
        if (is_object($disk) && method_exists($disk, 'path')) {
            return $disk->path($relativePath);
        }

        return storage_path('app/' . ltrim($relativePath, '/'));
    }

    private function assertZipIsValid(string $zipFullPath): void
    {
        clearstatcache(true, $zipFullPath);
        $size = @filesize($zipFullPath);
        if ($size === false || $size < 4) {
            throw new \RuntimeException('Generated ZIP file is empty or missing.');
        }

        $fh = @fopen($zipFullPath, 'rb');
        if (! $fh) {
            throw new \RuntimeException('Unable to read generated ZIP file.');
        }
        try {
            $sig = fread($fh, 2);
        } finally {
            fclose($fh);
        }

        if ($sig !== 'PK') {
            throw new \RuntimeException('Generated file is not a valid ZIP (missing PK signature).');
        }
    }

    private function simpleMap(string $table, string $keyCol, string $valCol, int $business_id): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }
        if (! (Schema::hasColumn($table, $keyCol) && Schema::hasColumn($table, $valCol))) {
            return [];
        }

        $q = DB::table($table)->select([$keyCol, $valCol]);
        if (Schema::hasColumn($table, 'business_id')) {
            $q->where('business_id', $business_id);
        }
        $rows = $q->get();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->{$keyCol}] = (string) $r->{$valCol};
        }
        return $map;
    }
}
