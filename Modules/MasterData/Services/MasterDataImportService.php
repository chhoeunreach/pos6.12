<?php

namespace Modules\MasterData\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class MasterDataImportService
{
    private const TEMP_DIR = 'temp/master_data';

    public function preview(string $zip_path, int $business_id, string $mode): array
    {
        $warnings = [];
        $parsed = $this->readZip($zip_path, $warnings);

        $manifest = $parsed['manifest'] ?? [];
        $data = $parsed['data'] ?? [];
        $sections = $manifest['sections'] ?? [];

        $recordCounts = $this->buildRecordCountsFromData($data, $sections);
        $sectionStats = $this->buildSectionStats($data, $sections, $business_id, $mode, $warnings);

        return [
            'sections' => $sections,
            'record_counts' => $recordCounts,
            'section_stats' => $sectionStats,
            'warnings' => $warnings,
        ];
    }

    public function import(string $zip_path, int $business_id, string $mode, array $options): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is required.');
        }

        $warnings = [];
        $parsed = $this->readZip($zip_path, $warnings);
        $manifest = $parsed['manifest'] ?? [];
        $data = $parsed['data'] ?? [];

        $sections = $manifest['sections'] ?? [];
        if (! is_array($sections) || empty($sections)) {
            throw new \RuntimeException('No sections found in manifest.');
        }

        $requestedBy = (int) ($options['requested_by'] ?? auth()->id() ?? 0);
        if ($requestedBy <= 0) {
            $requestedBy = (int) (request()->session()->get('user.id') ?? 0);
        }

        $result = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'warnings' => $warnings,
            'warnings_count' => count($warnings),
            'sections' => $sections,
        ];

        DB::beginTransaction();
        try {
            // Order matters for products re-mapping
            $order = ['categories', 'brands', 'units', 'taxes', 'locations', 'products', 'users', 'settings'];
            foreach ($order as $section) {
                if (! in_array($section, $sections, true)) {
                    continue;
                }
                $payload = $data[$section] ?? ($section === 'settings' ? ($data['settings'] ?? []) : []);
                $summary = $this->restoreSection($section, $payload, $business_id, $requestedBy, $mode, $warnings);
                foreach (['inserted', 'updated', 'skipped', 'failed'] as $k) {
                    $result[$k] += (int) ($summary[$k] ?? 0);
                }
            }

            $result['warnings'] = $warnings;
            $result['warnings_count'] = count($warnings);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $this->cleanupZipArtifact($zip_path);
        }

        return $result;
    }

    private function restoreSection(string $section, $payload, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        switch ($section) {
            case 'users':
                return $this->restoreUsers((array) $payload, $business_id, $requestedBy, $mode);
            case 'categories':
                return $this->restoreCategories((array) $payload, $business_id, $requestedBy, $mode, $warnings);
            case 'brands':
                return $this->restoreSimpleByName('brands', (array) $payload, $business_id, $requestedBy, $mode, $warnings);
            case 'units':
                return $this->restoreUnits((array) $payload, $business_id, $requestedBy, $mode, $warnings);
            case 'taxes':
                return $this->restoreTaxes((array) $payload, $business_id, $requestedBy, $mode, $warnings);
            case 'locations':
                return $this->restoreBusinessLocations((array) $payload, $business_id, $requestedBy, $mode, $warnings);
            case 'products':
                return $this->restoreProducts((array) $payload, $business_id, $requestedBy, $mode, $warnings);
            case 'settings':
                return $this->restoreSettings((array) $payload, $business_id, $requestedBy, $mode, $warnings);
        }

        return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
    }

    private function restoreUsers(array $users, int $business_id, int $requestedBy, string $mode): array
    {
        if (! Schema::hasTable('users')) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $cols = Schema::getColumnListing('users');

        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($users as $u) {
            try {
                $existing = $this->matchExistingUser((array) $u, $business_id);
                $isMatch = ! empty($existing);

                if ($isMatch && $mode === 'insert_only') {
                    $out['skipped']++;
                    continue;
                }
                if (! $isMatch && $mode === 'update_existing') {
                    $out['skipped']++;
                    continue;
                }

                $data = $this->prepareRowForInsertOrUpdate('users', (array) $u, $business_id, $requestedBy, $cols, [
                    'ignore' => ['id', 'business_id', 'password', 'remember_token', 'deleted_at'],
                ]);

                // password: do not restore old password; generate random if inserting and password column exists
                if (! $isMatch && in_array('password', $cols, true)) {
                    $data['password'] = bcrypt(Str::random(12));
                }

                if ($isMatch) {
                    if ($mode === 'update_existing' || $mode === 'insert_update') {
                        DB::table('users')->where('id', $existing['id'])->update($data);
                        $out['updated']++;
                    } else {
                        $out['skipped']++;
                    }
                } else {
                    $newId = DB::table('users')->insertGetId($data);
                    if (in_array('business_id', $cols, true)) {
                        DB::table('users')->where('id', $newId)->update(['business_id' => $business_id]);
                    }
                    $out['inserted']++;
                }
            } catch (\Throwable $e) {
                $out['failed']++;
            }
        }

        return $out;
    }

    private function restoreCategories(array $categories, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        if (! Schema::hasTable('categories')) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $cols = Schema::getColumnListing('categories');
        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        // First pass: create/update by name without relying on parent_id
        foreach ($categories as $c) {
            try {
                $c = (array) $c;
                $name = trim((string) ($c['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $existing = (array) DB::table('categories')
                    ->where('business_id', $business_id)
                    ->where('name', $name)
                    ->first();
                $isMatch = ! empty($existing);

                if ($isMatch && $mode === 'insert_only') {
                    $out['skipped']++;
                    continue;
                }
                if (! $isMatch && $mode === 'update_existing') {
                    $out['skipped']++;
                    continue;
                }

                $data = $this->prepareRowForInsertOrUpdate('categories', $c, $business_id, $requestedBy, $cols, [
                    'ignore' => ['id', 'business_id', 'parent_id', 'deleted_at'],
                ]);

                if (in_array('parent_id', $cols, true)) {
                    // temporarily set to 0; second pass will correct using _parent_name
                    $data['parent_id'] = 0;
                }

                if ($isMatch) {
                    if ($mode === 'update_existing' || $mode === 'insert_update') {
                        DB::table('categories')->where('id', $existing['id'])->update($data);
                        $out['updated']++;
                    } else {
                        $out['skipped']++;
                    }
                } else {
                    DB::table('categories')->insert($data);
                    $out['inserted']++;
                }
            } catch (\Throwable $e) {
                $out['failed']++;
            }
        }

        // Second pass: parent mapping (by name)
        if (in_array('parent_id', $cols, true)) {
            foreach ($categories as $c) {
                $c = (array) $c;
                $name = trim((string) ($c['name'] ?? ''));
                $parentName = trim((string) ($c['_parent_name'] ?? ''));
                if ($name === '' || $parentName === '') {
                    continue;
                }
                $child = (array) DB::table('categories')->where('business_id', $business_id)->where('name', $name)->first();
                $parent = (array) DB::table('categories')->where('business_id', $business_id)->where('name', $parentName)->first();
                if (empty($child) || empty($parent)) {
                    if (empty($parent)) {
                        $warnings[] = 'Category parent not found, skipped parent mapping: ' . $parentName;
                    }
                    continue;
                }
                DB::table('categories')->where('id', $child['id'])->update(['parent_id' => $parent['id']]);
            }
        }

        return $out;
    }

    private function restoreSimpleByName(string $table, array $rows, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        if (! Schema::hasTable($table)) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $cols = Schema::getColumnListing($table);
        if (! in_array('name', $cols, true)) {
            $warnings[] = 'Table missing name column: ' . $table;
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($rows as $r) {
            try {
                $r = (array) $r;
                $name = trim((string) ($r['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $existing = (array) DB::table($table)
                    ->when(Schema::hasColumn($table, 'business_id'), fn ($q) => $q->where('business_id', $business_id))
                    ->where('name', $name)
                    ->first();
                $isMatch = ! empty($existing);

                if ($isMatch && $mode === 'insert_only') {
                    $out['skipped']++;
                    continue;
                }
                if (! $isMatch && $mode === 'update_existing') {
                    $out['skipped']++;
                    continue;
                }

                $data = $this->prepareRowForInsertOrUpdate($table, $r, $business_id, $requestedBy, $cols, [
                    'ignore' => ['id', 'business_id', 'deleted_at'],
                ]);

                if ($isMatch) {
                    if ($mode === 'update_existing' || $mode === 'insert_update') {
                        DB::table($table)->where('id', $existing['id'])->update($data);
                        $out['updated']++;
                    } else {
                        $out['skipped']++;
                    }
                } else {
                    DB::table($table)->insert($data);
                    $out['inserted']++;
                }
            } catch (\Throwable $e) {
                $out['failed']++;
            }
        }

        return $out;
    }

    private function restoreUnits(array $units, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        if (! Schema::hasTable('units')) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $cols = Schema::getColumnListing('units');

        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($units as $u) {
            try {
                $u = (array) $u;
                $short = trim((string) ($u['short_name'] ?? ''));
                $actual = trim((string) ($u['actual_name'] ?? ''));
                if ($short === '' && $actual === '') {
                    continue;
                }

                $existingQ = DB::table('units');
                if (Schema::hasColumn('units', 'business_id')) {
                    $existingQ->where('business_id', $business_id);
                }
                if ($short !== '' && Schema::hasColumn('units', 'short_name')) {
                    $existingQ->where('short_name', $short);
                } else {
                    $existingQ->where('actual_name', $actual);
                }
                $existing = (array) $existingQ->first();
                $isMatch = ! empty($existing);

                if ($isMatch && $mode === 'insert_only') {
                    $out['skipped']++;
                    continue;
                }
                if (! $isMatch && $mode === 'update_existing') {
                    $out['skipped']++;
                    continue;
                }

                $data = $this->prepareRowForInsertOrUpdate('units', $u, $business_id, $requestedBy, $cols, [
                    'ignore' => ['id', 'business_id', 'deleted_at'],
                ]);

                if ($isMatch) {
                    if ($mode === 'update_existing' || $mode === 'insert_update') {
                        DB::table('units')->where('id', $existing['id'])->update($data);
                        $out['updated']++;
                    } else {
                        $out['skipped']++;
                    }
                } else {
                    DB::table('units')->insert($data);
                    $out['inserted']++;
                }
            } catch (\Throwable $e) {
                $out['failed']++;
            }
        }

        return $out;
    }

    private function restoreTaxes(array $taxes, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        if (! Schema::hasTable('tax_rates')) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $cols = Schema::getColumnListing('tax_rates');
        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($taxes as $t) {
            try {
                $t = (array) $t;
                $name = trim((string) ($t['name'] ?? ''));
                $amount = $t['amount'] ?? null;
                if ($name === '' || $amount === null) {
                    continue;
                }

                $existing = (array) DB::table('tax_rates')
                    ->where('business_id', $business_id)
                    ->where('name', $name)
                    ->where('amount', $amount)
                    ->first();
                $isMatch = ! empty($existing);

                if ($isMatch && $mode === 'insert_only') {
                    $out['skipped']++;
                    continue;
                }
                if (! $isMatch && $mode === 'update_existing') {
                    $out['skipped']++;
                    continue;
                }

                $data = $this->prepareRowForInsertOrUpdate('tax_rates', $t, $business_id, $requestedBy, $cols, [
                    'ignore' => ['id', 'business_id', 'deleted_at'],
                ]);

                if ($isMatch) {
                    if ($mode === 'update_existing' || $mode === 'insert_update') {
                        DB::table('tax_rates')->where('id', $existing['id'])->update($data);
                        $out['updated']++;
                    } else {
                        $out['skipped']++;
                    }
                } else {
                    DB::table('tax_rates')->insert($data);
                    $out['inserted']++;
                }
            } catch (\Throwable $e) {
                $out['failed']++;
            }
        }

        return $out;
    }

    private function restoreProducts(array $products, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        if (! Schema::hasTable('products')) {
            $warnings[] = 'Products table not found; skipped products restore.';
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        if (! (Schema::hasTable('product_variations') && Schema::hasTable('variations'))) {
            $warnings[] = 'Product variations tables not found; skipped products restore.';
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $productCols = Schema::getColumnListing('products');
        $pvCols = Schema::getColumnListing('product_variations');
        $varCols = Schema::getColumnListing('variations');

        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($products as $bundle) {
            try {
                $bundle = (array) $bundle;
                $p = (array) ($bundle['product'] ?? []);
                $pvs = (array) ($bundle['product_variations'] ?? []);
                $vars = (array) ($bundle['variations'] ?? []);

                $sku = trim((string) ($p['sku'] ?? ''));
                $name = trim((string) ($p['name'] ?? ''));
                if ($sku === '' && $name === '') {
                    continue;
                }

                $existing = $this->matchExistingProduct($p, $business_id);
                $isMatch = ! empty($existing);

                if ($isMatch && $mode === 'insert_only') {
                    $out['skipped']++;
                    continue;
                }
                if (! $isMatch && $mode === 'update_existing') {
                    $out['skipped']++;
                    continue;
                }

                $data = $this->prepareRowForInsertOrUpdate('products', $p, $business_id, $requestedBy, $productCols, [
                    'ignore' => ['id', 'business_id'],
                ]);

                // Remap relations by name (do not use old IDs)
                $data = $this->remapProductRelations($data, $p, $business_id, $warnings);

                if ($isMatch) {
                    if ($mode === 'update_existing' || $mode === 'insert_update') {
                        DB::table('products')->where('id', $existing['id'])->update($data);
                        $productId = (int) $existing['id'];
                        $out['updated']++;
                    } else {
                        $out['skipped']++;
                        continue;
                    }
                } else {
                    $productId = (int) DB::table('products')->insertGetId($data);
                    $out['inserted']++;
                }

                // Restore product_variations
                $pvMap = $this->restoreProductVariations($productId, $pvs, $business_id, $requestedBy, $pvCols, $mode);

                // Restore variations
                $this->restoreVariations($productId, $vars, $pvMap, $business_id, $requestedBy, $varCols, $mode, $warnings);
            } catch (\Throwable $e) {
                $out['failed']++;
            }
        }

        return $out;
    }

    private function restoreBusinessLocations(array $locations, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        if (! Schema::hasTable('business_locations')) {
            $warnings[] = 'Business locations table not found; skipped locations restore.';
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $cols = Schema::getColumnListing('business_locations');
        if (! in_array('name', $cols, true)) {
            $warnings[] = 'Business locations table missing name column; skipped.';
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($locations as $loc) {
            try {
                $loc = (array) $loc;
                $name = trim((string) ($loc['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $existingQ = DB::table('business_locations')->where('name', $name);
                if (Schema::hasColumn('business_locations', 'business_id')) {
                    $existingQ->where('business_id', $business_id);
                }
                $existing = (array) $existingQ->first();
                $isMatch = ! empty($existing);

                if ($isMatch && $mode === 'insert_only') {
                    $out['skipped']++;
                    continue;
                }
                if (! $isMatch && $mode === 'update_existing') {
                    $out['skipped']++;
                    continue;
                }

                $data = $this->prepareRowForInsertOrUpdate('business_locations', $loc, $business_id, $requestedBy, $cols, [
                    'ignore' => ['id', 'business_id', 'deleted_at'],
                ]);

                // Never trust foreign IDs for printer/schemes/layouts; keep only if those IDs exist locally
                foreach (['invoice_scheme_id', 'invoice_layout_id', 'sale_invoice_layout_id', 'selling_price_group_id', 'printer_id'] as $fk) {
                    if (array_key_exists($fk, $data) && ! empty($data[$fk])) {
                        $data[$fk] = null;
                    }
                }

                if ($isMatch) {
                    if ($mode === 'update_existing' || $mode === 'insert_update') {
                        DB::table('business_locations')->where('id', $existing['id'])->update($data);
                        $out['updated']++;
                    } else {
                        $out['skipped']++;
                    }
                } else {
                    DB::table('business_locations')->insert($data);
                    $out['inserted']++;
                }
            } catch (\Throwable $e) {
                $out['failed']++;
            }
        }

        return $out;
    }

    private function restoreSettings(array $settings, int $business_id, int $requestedBy, string $mode, array &$warnings): array
    {
        if (! Schema::hasTable('business')) {
            $warnings[] = 'Business table not found; skipped settings restore.';
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $cols = Schema::getColumnListing('business');
        $existing = (array) DB::table('business')->where('id', $business_id)->first();
        if (empty($existing)) {
            $warnings[] = 'Current business not found; skipped settings restore.';
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $data = [];
        foreach ($settings as $k => $v) {
            if (! in_array($k, $cols, true)) {
                continue;
            }
            if (in_array($k, ['id', 'owner_id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $data[$k] = $v;
        }
        if (Schema::hasColumn('business', 'updated_at')) {
            $data['updated_at'] = now();
        }
        if (Schema::hasColumn('business', 'owner_id')) {
            $data['owner_id'] = $requestedBy;
        }

        DB::table('business')->where('id', $business_id)->update($data);

        return ['inserted' => 0, 'updated' => 1, 'skipped' => 0, 'failed' => 0];
    }

    private function matchExistingUser(array $u, int $business_id): ?array
    {
        $q = DB::table('users');
        if (Schema::hasColumn('users', 'business_id')) {
            $q->where('business_id', $business_id);
        }

        $email = trim((string) ($u['email'] ?? ''));
        $username = trim((string) ($u['username'] ?? ''));
        $contact = trim((string) ($u['contact_no'] ?? ($u['contact_number'] ?? '')));

        if ($email !== '' && Schema::hasColumn('users', 'email')) {
            $found = (array) (clone $q)->where('email', $email)->first();
            if (! empty($found)) {
                return $found;
            }
        }
        if ($username !== '' && Schema::hasColumn('users', 'username')) {
            $found = (array) (clone $q)->where('username', $username)->first();
            if (! empty($found)) {
                return $found;
            }
        }
        if ($contact !== '' && (Schema::hasColumn('users', 'contact_no') || Schema::hasColumn('users', 'contact_number'))) {
            $found = (array) (clone $q)->where(function ($w) use ($contact) {
                if (Schema::hasColumn('users', 'contact_no')) {
                    $w->orWhere('contact_no', $contact);
                }
                if (Schema::hasColumn('users', 'contact_number')) {
                    $w->orWhere('contact_number', $contact);
                }
            })->first();
            if (! empty($found)) {
                return $found;
            }
        }

        return null;
    }

    private function matchExistingProduct(array $p, int $business_id): ?array
    {
        $q = DB::table('products')->where('business_id', $business_id);

        $sku = trim((string) ($p['sku'] ?? ''));
        $name = trim((string) ($p['name'] ?? ''));

        if ($sku !== '' && Schema::hasColumn('products', 'sku')) {
            $found = (array) (clone $q)->where('sku', $sku)->first();
            if (! empty($found)) {
                return $found;
            }
        }
        if ($name !== '' && Schema::hasColumn('products', 'name')) {
            $found = (array) (clone $q)->where('name', $name)->first();
            if (! empty($found)) {
                return $found;
            }
        }

        return null;
    }

    private function prepareRowForInsertOrUpdate(string $table, array $row, int $business_id, int $requestedBy, array $cols, array $opts): array
    {
        $ignore = $opts['ignore'] ?? [];
        $data = [];
        foreach ($row as $k => $v) {
            if (Str::startsWith((string) $k, '_')) {
                continue;
            }
            if (in_array($k, $ignore, true)) {
                continue;
            }
            if (! in_array($k, $cols, true)) {
                continue;
            }
            $data[$k] = $v;
        }

        if (Schema::hasColumn($table, 'business_id')) {
            $data['business_id'] = $business_id;
        }
        if (Schema::hasColumn($table, 'created_by')) {
            $data['created_by'] = $requestedBy;
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $data['updated_at'] = now();
        }
        if (Schema::hasColumn($table, 'created_at') && empty($data['created_at'])) {
            $data['created_at'] = now();
        }

        return $data;
    }

    private function remapProductRelations(array $data, array $sourceProduct, int $business_id, array &$warnings): array
    {
        // Category
        if (Schema::hasColumn('products', 'category_id')) {
            $catName = trim((string) ($sourceProduct['_category_name'] ?? ''));
            if ($catName !== '' && Schema::hasTable('categories')) {
                $cat = (array) DB::table('categories')->where('business_id', $business_id)->where('name', $catName)->first();
                if (! empty($cat)) {
                    $data['category_id'] = $cat['id'];
                } else {
                    $data['category_id'] = null;
                    $warnings[] = 'Category not found for product, set null: ' . $catName;
                }
            } else {
                $data['category_id'] = null;
            }
        }

        // Brand
        if (Schema::hasColumn('products', 'brand_id')) {
            $brandName = trim((string) ($sourceProduct['_brand_name'] ?? ''));
            if ($brandName !== '' && Schema::hasTable('brands')) {
                $brand = (array) DB::table('brands')->where('business_id', $business_id)->where('name', $brandName)->first();
                $data['brand_id'] = ! empty($brand) ? $brand['id'] : null;
                if (empty($brand) && $brandName !== '') {
                    $warnings[] = 'Brand not found for product, set null: ' . $brandName;
                }
            } else {
                $data['brand_id'] = null;
            }
        }

        // Unit (required in base schema)
        if (Schema::hasColumn('products', 'unit_id')) {
            $unitShort = trim((string) ($sourceProduct['_unit_short_name'] ?? ''));
            if ($unitShort !== '' && Schema::hasTable('units') && Schema::hasColumn('units', 'short_name')) {
                $unit = (array) DB::table('units')->where('business_id', $business_id)->where('short_name', $unitShort)->first();
                if (! empty($unit)) {
                    $data['unit_id'] = $unit['id'];
                } else {
                    // If not found, try actual_name fallback
                    $unit2 = (array) DB::table('units')->where('business_id', $business_id)->where('actual_name', $unitShort)->first();
                    if (! empty($unit2)) {
                        $data['unit_id'] = $unit2['id'];
                    } else {
                        $warnings[] = 'Unit not found for product: ' . $unitShort;
                    }
                }
            }
        }

        // Tax
        if (Schema::hasColumn('products', 'tax')) {
            $taxName = trim((string) ($sourceProduct['_tax_name'] ?? ''));
            if ($taxName !== '' && Schema::hasTable('tax_rates')) {
                $tax = (array) DB::table('tax_rates')->where('business_id', $business_id)->where('name', $taxName)->first();
                $data['tax'] = ! empty($tax) ? $tax['id'] : null;
                if (empty($tax) && $taxName !== '') {
                    $warnings[] = 'Tax not found for product, set null: ' . $taxName;
                }
            } else {
                $data['tax'] = null;
            }
        }

        return $data;
    }

    private function restoreProductVariations(int $productId, array $pvs, int $business_id, int $requestedBy, array $pvCols, string $mode): array
    {
        // Returns map from old pv id => new pv id
        $map = [];
        foreach ($pvs as $pv) {
            $pv = (array) $pv;
            $name = trim((string) ($pv['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $existing = (array) DB::table('product_variations')->where('product_id', $productId)->where('name', $name)->first();
            $isMatch = ! empty($existing);

            if (! $isMatch && $mode === 'update_existing') {
                continue;
            }

            $data = [];
            foreach ($pv as $k => $v) {
                if (Str::startsWith((string) $k, '_')) {
                    continue;
                }
                if (in_array($k, ['id', 'product_id'], true)) {
                    continue;
                }
                if (! in_array($k, $pvCols, true)) {
                    continue;
                }
                $data[$k] = $v;
            }
            $data['product_id'] = $productId;
            if (in_array('updated_at', $pvCols, true)) {
                $data['updated_at'] = now();
            }
            if (in_array('created_at', $pvCols, true) && empty($data['created_at'])) {
                $data['created_at'] = now();
            }

            if ($isMatch) {
                if ($mode === 'update_existing' || $mode === 'insert_update') {
                    DB::table('product_variations')->where('id', $existing['id'])->update($data);
                }
                $newId = (int) $existing['id'];
            } else {
                $newId = (int) DB::table('product_variations')->insertGetId($data);
            }

            if (! empty($pv['id'])) {
                $map[(int) $pv['id']] = $newId;
            }
        }

        return $map;
    }

    private function restoreVariations(int $productId, array $vars, array $pvIdMap, int $business_id, int $requestedBy, array $varCols, string $mode, array &$warnings): void
    {
        foreach ($vars as $v) {
            $v = (array) $v;

            $subSku = trim((string) ($v['sub_sku'] ?? ''));
            $name = trim((string) ($v['name'] ?? ''));
            if ($subSku === '' && $name === '') {
                continue;
            }

            $existingQ = DB::table('variations')->where('product_id', $productId);
            if ($subSku !== '' && Schema::hasColumn('variations', 'sub_sku')) {
                $existingQ->where('sub_sku', $subSku);
            } else {
                $existingQ->where('name', $name);
            }
            $existing = (array) $existingQ->first();
            $isMatch = ! empty($existing);

            if ($isMatch && $mode === 'insert_only') {
                continue;
            }
            if (! $isMatch && $mode === 'update_existing') {
                continue;
            }

            $data = [];
            foreach ($v as $k => $val) {
                if (Str::startsWith((string) $k, '_')) {
                    continue;
                }
                if (in_array($k, ['id', 'product_id'], true)) {
                    continue;
                }
                if (! in_array($k, $varCols, true)) {
                    continue;
                }
                $data[$k] = $val;
            }

            $data['product_id'] = $productId;

            // Remap product_variation_id
            if (isset($data['product_variation_id'])) {
                $oldPv = (int) $data['product_variation_id'];
                if (isset($pvIdMap[$oldPv])) {
                    $data['product_variation_id'] = $pvIdMap[$oldPv];
                } else {
                    // Fallback: try to map by name if possible
                    $warnings[] = 'Variation product_variation_id mapping missing; keeping existing relation where possible.';
                }
            }

            if (in_array('updated_at', $varCols, true)) {
                $data['updated_at'] = now();
            }
            if (in_array('created_at', $varCols, true) && empty($data['created_at'])) {
                $data['created_at'] = now();
            }

            if ($isMatch) {
                if ($mode === 'update_existing' || $mode === 'insert_update') {
                    DB::table('variations')->where('id', $existing['id'])->update($data);
                }
            } else {
                // Avoid duplicate sub_sku globally if there's a unique index in some builds
                if ($subSku !== '' && Schema::hasColumn('variations', 'sub_sku')) {
                    $dup = (array) DB::table('variations')->where('sub_sku', $subSku)->first();
                    if (! empty($dup)) {
                        $warnings[] = 'Duplicate sub_sku exists; skipped variation insert: ' . $subSku;
                        continue;
                    }
                }
                DB::table('variations')->insert($data);
            }
        }
    }

    private function buildRecordCountsFromData(array $data, array $sections): array
    {
        $counts = [];
        foreach ($sections as $s) {
            if ($s === 'settings') {
                $counts[$s] = ! empty($data['settings']) ? 1 : 0;
                continue;
            }
            $counts[$s] = is_array($data[$s] ?? null) ? count($data[$s]) : 0;
        }
        return $counts;
    }

    private function buildSectionStats(array $data, array $sections, int $business_id, string $mode, array &$warnings): array
    {
        $stats = [];
        foreach ($sections as $s) {
            $rows = $data[$s] ?? ($s === 'settings' ? ($data['settings'] ?? []) : []);

            $new = 0;
            $matched = 0;

            if ($s === 'users') {
                foreach ((array) $rows as $u) {
                    $m = $this->matchExistingUser((array) $u, $business_id);
                    if (! empty($m)) {
                        $matched++;
                    } else {
                        $new++;
                    }
                }
            } elseif ($s === 'categories') {
                if (Schema::hasTable('categories')) {
                    foreach ((array) $rows as $c) {
                        $name = trim((string) (($c['name'] ?? null) ?: ''));
                        if ($name === '') {
                            continue;
                        }
                        $m = (array) DB::table('categories')->where('business_id', $business_id)->where('name', $name)->first();
                        if (! empty($m)) {
                            $matched++;
                        } else {
                            $new++;
                        }
                    }
                } else {
                    $warnings[] = 'Categories table not found; preview match skipped.';
                }
            } elseif ($s === 'brands') {
                $this->previewSimpleByName('brands', (array) $rows, $business_id, $new, $matched, $warnings);
            } elseif ($s === 'units') {
                if (Schema::hasTable('units')) {
                    foreach ((array) $rows as $u) {
                        $short = trim((string) (($u['short_name'] ?? null) ?: ''));
                        $actual = trim((string) (($u['actual_name'] ?? null) ?: ''));
                        if ($short === '' && $actual === '') {
                            continue;
                        }
                        $q = DB::table('units')->where('business_id', $business_id);
                        if ($short !== '' && Schema::hasColumn('units', 'short_name')) {
                            $q->where('short_name', $short);
                        } else {
                            $q->where('actual_name', $actual);
                        }
                        $m = (array) $q->first();
                        if (! empty($m)) {
                            $matched++;
                        } else {
                            $new++;
                        }
                    }
                } else {
                    $warnings[] = 'Units table not found; preview match skipped.';
                }
            } elseif ($s === 'taxes') {
                if (Schema::hasTable('tax_rates')) {
                    foreach ((array) $rows as $t) {
                        $name = trim((string) (($t['name'] ?? null) ?: ''));
                        $amount = $t['amount'] ?? null;
                        if ($name === '' || $amount === null) {
                            continue;
                        }
                        $m = (array) DB::table('tax_rates')->where('business_id', $business_id)->where('name', $name)->where('amount', $amount)->first();
                        if (! empty($m)) {
                            $matched++;
                        } else {
                            $new++;
                        }
                    }
                } else {
                    $warnings[] = 'Tax rates table not found; preview match skipped.';
                }
            } elseif ($s === 'products') {
                if (Schema::hasTable('products')) {
                    foreach ((array) $rows as $bundle) {
                        $bundle = (array) $bundle;
                        $p = (array) ($bundle['product'] ?? []);
                        $m = $this->matchExistingProduct($p, $business_id);
                        if (! empty($m)) {
                            $matched++;
                        } else {
                            $new++;
                        }
                    }
                } else {
                    $warnings[] = 'Products table not found; preview match skipped.';
                }
            } elseif ($s === 'locations') {
                if (Schema::hasTable('business_locations')) {
                    foreach ((array) $rows as $loc) {
                        $loc = (array) $loc;
                        $name = trim((string) (($loc['name'] ?? null) ?: ''));
                        if ($name === '') {
                            continue;
                        }
                        $q = DB::table('business_locations')->where('name', $name);
                        if (Schema::hasColumn('business_locations', 'business_id')) {
                            $q->where('business_id', $business_id);
                        }
                        $m = (array) $q->first();
                        if (! empty($m)) {
                            $matched++;
                        } else {
                            $new++;
                        }
                    }
                } else {
                    $warnings[] = 'Business locations table not found; preview match skipped.';
                }
            } elseif ($s === 'settings') {
                $new = 0;
                $matched = 1;
            }

            $willInsert = $new;
            $willUpdate = 0;
            $willSkip = 0;
            if ($mode === 'insert_only') {
                $willUpdate = 0;
                $willSkip = $matched;
            } elseif ($mode === 'update_existing') {
                $willUpdate = $matched;
                $willSkip = $new; // would not insert new in update-only mode
                $willInsert = 0;
            } elseif ($mode === 'insert_update') {
                $willUpdate = $matched;
                $willSkip = 0;
                $willInsert = $new;
            }

            $stats[] = [
                'section' => $s,
                'new' => $new,
                'matched' => $matched,
                'will_insert' => $willInsert,
                'will_update' => $willUpdate,
                'will_skip' => $willSkip,
            ];
        }

        return $stats;
    }

    private function previewSimpleByName(string $table, array $rows, int $business_id, int &$new, int &$matched, array &$warnings): void
    {
        if (! Schema::hasTable($table)) {
            $warnings[] = ucfirst($table) . ' table not found; preview match skipped.';
            return;
        }
        foreach ($rows as $r) {
            $r = (array) $r;
            $name = trim((string) (($r['name'] ?? null) ?: ''));
            if ($name === '') {
                continue;
            }
            $q = DB::table($table)->where('name', $name);
            if (Schema::hasColumn($table, 'business_id')) {
                $q->where('business_id', $business_id);
            }
            $m = (array) $q->first();
            if (! empty($m)) {
                $matched++;
            } else {
                $new++;
            }
        }
    }

    private function readZip(string $zip_path, array &$warnings): array
    {
        if (! is_file($zip_path)) {
            throw new \RuntimeException('ZIP file not found.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive extension is required.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            throw new \RuntimeException('Invalid ZIP file.');
        }

        $runId = now()->format('Ymd_His') . '_' . Str::random(8);
        $extractRel = self::TEMP_DIR . '/extracts/' . $runId;
        Storage::disk('local')->makeDirectory($extractRel);
        $extractPath = storage_path('app/' . $extractRel);

        try {
            if ($zip->locateName('manifest.json') === false) {
                throw new \RuntimeException('ZIP missing manifest.json');
            }
            if ($zip->locateName('data.json') === false) {
                throw new \RuntimeException('ZIP missing data.json');
            }

            $zip->extractTo($extractPath, ['manifest.json', 'data.json']);
            $zip->close();

            $manifestRaw = file_get_contents($extractPath . '/manifest.json');
            $dataRaw = file_get_contents($extractPath . '/data.json');

            $manifest = json_decode((string) $manifestRaw, true);
            if (! is_array($manifest)) {
                throw new \RuntimeException('Invalid manifest.json format.');
            }
            if (($manifest['module'] ?? null) !== 'MasterData') {
                $warnings[] = 'Manifest module mismatch: ' . (string) ($manifest['module'] ?? '');
            }

            $data = json_decode((string) $dataRaw, true);
            if (! is_array($data)) {
                throw new \RuntimeException('Invalid data.json format.');
            }

            return [
                'manifest' => $manifest,
                'data' => $data,
            ];
        } finally {
            Storage::disk('local')->deleteDirectory($extractRel);
        }
    }

    private function cleanupZipArtifact(string $zip_path): void
    {
        $real = realpath($zip_path);
        if (! $real) {
            return;
        }
        $tempRoot = realpath(storage_path('app/' . self::TEMP_DIR));
        if ($tempRoot && Str::startsWith($real, $tempRoot)) {
            @unlink($real);
        }
    }
}
