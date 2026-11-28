<?php

namespace Modules\SmartStockInventory\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartStockPriceCompare;

class PriceCompareService
{
    public function ensureTablesExist(): void
    {
        if (! Schema::hasTable('smart_stock_price_compares')) {
            Schema::create('smart_stock_price_compares', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->string('ref_no', 60)->index();
                $table->string('title', 191)->nullable();
                $table->date('compare_date')->index();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'ref_no'], 'sspc_cmp_biz_ref_uq');
                $table->index(['business_id', 'compare_date'], 'sspc_cmp_biz_date_idx');
            });
        }

        if (! Schema::hasTable('smart_stock_price_compare_lines')) {
            Schema::create('smart_stock_price_compare_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('price_compare_id')->index();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('product_id')->index();
                $table->unsignedInteger('variation_id')->index();
                $table->string('sku', 191)->index();
                $table->decimal('qty', 22, 4)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['price_compare_id', 'sort_order'], 'sspc_lines_cmp_sort_idx');
                $table->index(['business_id', 'variation_id'], 'sspc_lines_biz_var_idx');
            });
        }
    }

    public function generateRefNo(int $businessId): string
    {
        $prefix = 'CP-' . now()->format('Ymd') . '-';
        $latest = SmartStockPriceCompare::withTrashed()
            ->where('business_id', $businessId)
            ->where('ref_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('ref_no');

        $seq = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function parseUploadedFile(UploadedFile $file): array
    {
        $sheets = Excel::toArray([], $file);
        $sheetRows = $sheets[0] ?? [];
        if (empty($sheetRows)) {
            return [];
        }

        $firstRow = array_map(fn ($cell) => strtolower(trim((string) $cell)), (array) ($sheetRows[0] ?? []));
        $skuCol = null;
        $qtyCol = null;

        foreach ($firstRow as $idx => $header) {
            if ($skuCol === null && in_array($header, ['sku', 'sub_sku', 'product_sku', 'barcode', 'item_sku', 'កូដ', 'លេខកូដ'], true)) {
                $skuCol = $idx;
            }
            if ($qtyCol === null && in_array($header, ['qty', 'quantity', 'qty_ordered', 'order_qty', 'ចំនួន'], true)) {
                $qtyCol = $idx;
            }
        }

        $hasHeader = $skuCol !== null;
        $skuCol = $skuCol ?? 0;
        $qtyCol = $qtyCol ?? 1;

        $items = [];
        foreach ($sheetRows as $rowIndex => $row) {
            if ($hasHeader && $rowIndex === 0) {
                continue;
            }
            $row = array_values((array) $row);
            $sku = trim((string) ($row[$skuCol] ?? ''));
            if ($sku === '') {
                continue;
            }
            $rawQty = trim((string) ($row[$qtyCol] ?? ''));
            $qty = $rawQty === '' ? 1.0 : $this->parseNumber($rawQty);

            $items[] = [
                'row' => $rowIndex + 1,
                'sku' => $sku,
                'qty' => $qty,
            ];
        }

        return $items;
    }

    public function parsePastedText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $items = [];

        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_contains($line, "\t")) {
                $parts = explode("\t", $line);
            } elseif (str_contains($line, ',')) {
                $parts = str_getcsv($line);
            } elseif (str_contains($line, ';')) {
                $parts = explode(';', $line);
            } else {
                $parts = preg_split('/\s+/', $line) ?: [$line];
            }

            $sku = trim((string) ($parts[0] ?? ''));
            if ($sku === '') {
                continue;
            }

            if ($idx === 0 && in_array(strtolower($sku), ['sku', 'sub_sku', 'product_sku', 'barcode', 'កូដ', 'លេខកូដ'], true)) {
                continue;
            }

            $rawQty = trim((string) ($parts[1] ?? ''));
            $qty = $rawQty === '' ? 1.0 : $this->parseNumber($rawQty);

            $items[] = [
                'row' => $idx + 1,
                'sku' => $sku,
                'qty' => $qty,
            ];
        }

        return $items;
    }

    public function resolveSkus(int $businessId, array $rawItems): array
    {
        $cleanInputs = [];
        foreach ($rawItems as $idx => $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $qty = isset($item['qty']) && $item['qty'] !== '' ? (float) $item['qty'] : 1.0;
            $cleanInputs[] = [
                'row' => (int) ($item['row'] ?? ($idx + 1)),
                'sku' => $sku,
                'qty' => max(0.0, $qty),
            ];
        }

        if (empty($cleanInputs)) {
            return ['matched' => [], 'missing' => []];
        }

        $lowerSkus = collect($cleanInputs)->pluck('sku')->map(fn ($s) => mb_strtolower($s))->unique()->values()->all();

        // Match variations.sub_sku first, then products.sku across the business
        $variationMatches = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.business_id', $businessId)
            ->whereNull('v.deleted_at')
            ->where(function ($q) use ($lowerSkus) {
                $q->whereIn(DB::raw('LOWER(v.sub_sku)'), $lowerSkus)
                    ->orWhereIn(DB::raw('LOWER(p.sku)'), $lowerSkus);
            })
            ->orderBy('v.id')
            ->get([
                'v.id as variation_id',
                'v.product_id',
                'v.sub_sku',
                'v.name as variation_name',
                'p.sku as product_sku',
                'p.name as product_name',
                'u.short_name as unit',
            ]);

        $bySubSku = [];
        $byProductSku = [];
        foreach ($variationMatches as $row) {
            $subKey = mb_strtolower(trim((string) $row->sub_sku));
            if ($subKey !== '' && ! isset($bySubSku[$subKey])) {
                $bySubSku[$subKey] = $row;
            }
            $prodKey = mb_strtolower(trim((string) $row->product_sku));
            if ($prodKey !== '' && ! isset($byProductSku[$prodKey])) {
                $byProductSku[$prodKey] = $row;
            }
        }

        $matchedMap = [];
        $missing = [];

        foreach ($cleanInputs as $input) {
            $key = mb_strtolower($input['sku']);
            $found = $bySubSku[$key] ?? $byProductSku[$key] ?? null;
            if (! $found) {
                $missing[] = [
                    'row' => $input['row'],
                    'sku' => $input['sku'],
                    'qty' => $input['qty'],
                ];
                continue;
            }

            $varId = (int) $found->variation_id;
            if (! isset($matchedMap[$varId])) {
                $variantName = (string) ($found->variation_name ?? '');
                $matchedMap[$varId] = [
                    'variation_id' => $varId,
                    'product_id' => (int) $found->product_id,
                    'sku' => (string) ($found->sub_sku ?: $found->product_sku),
                    'product_name' => (string) $found->product_name,
                    'variant' => $variantName === 'DUMMY' ? '' : $variantName,
                    'unit' => (string) ($found->unit ?? ''),
                    'qty' => 0.0,
                ];
            }
            $matchedMap[$varId]['qty'] += (float) $input['qty'];
        }

        return [
            'matched' => array_values($matchedMap),
            'missing' => $missing,
        ];
    }

    public function enrichWithRealPurchasePrices(
        int $businessId,
        iterable $items,
        array $permittedLocationIds,
        ?int $filterLocationId = null,
        bool $canViewCosts = true
    ): Collection {
        $rows = collect($items)->values();
        if ($rows->isEmpty()) {
            return collect();
        }

        $variationIds = $rows->pluck('variation_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        $productMeta = collect();
        if (! empty($variationIds)) {
            $productMeta = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
                ->where('p.business_id', $businessId)
                ->whereIn('v.id', $variationIds)
                ->get([
                    'v.id as variation_id',
                    'v.product_id',
                    'v.sub_sku',
                    'v.name as variation_name',
                    'p.sku as product_sku',
                    'p.name as product_name',
                    'u.short_name as unit',
                ])
                ->keyBy('variation_id');
        }

        $locationIds = $filterLocationId && $filterLocationId > 0
            ? [$filterLocationId]
            : array_values(array_unique(array_map('intval', $permittedLocationIds)));

        $statsByVariation = collect();
        $latestByVariation = [];

        if ($canViewCosts && ! empty($variationIds) && ! empty($locationIds)) {
            $statsByVariation = DB::table('purchase_lines as pl')
                ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'purchase')
                ->where('t.status', 'received')
                ->whereIn('t.location_id', $locationIds)
                ->whereIn('pl.variation_id', $variationIds)
                ->whereNotNull('pl.purchase_price_inc_tax')
                ->groupBy('pl.variation_id')
                ->selectRaw('
                    pl.variation_id,
                    MIN(pl.purchase_price_inc_tax) as min_price,
                    AVG(pl.purchase_price_inc_tax) as avg_price,
                    MAX(pl.purchase_price_inc_tax) as max_price,
                    COUNT(pl.id) as purchase_count
                ')
                ->get()
                ->keyBy('variation_id');

            $latestRows = DB::table('purchase_lines as pl')
                ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'purchase')
                ->where('t.status', 'received')
                ->whereIn('t.location_id', $locationIds)
                ->whereIn('pl.variation_id', $variationIds)
                ->whereNotNull('pl.purchase_price_inc_tax')
                ->orderByDesc('t.transaction_date')
                ->orderByDesc('t.id')
                ->orderByDesc('pl.id')
                ->get([
                    'pl.variation_id',
                    'pl.purchase_price_inc_tax as latest_price',
                    't.transaction_date as latest_date',
                    't.ref_no as latest_ref_no',
                    't.id as latest_transaction_id',
                    'c.name as latest_supplier',
                ]);

            foreach ($latestRows as $latestRow) {
                $vid = (int) $latestRow->variation_id;
                if (! isset($latestByVariation[$vid])) {
                    $latestByVariation[$vid] = $latestRow;
                }
            }
        }

        return $rows->map(function ($item) use ($productMeta, $statsByVariation, $latestByVariation, $canViewCosts) {
            $itemArr = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);
            $varId = (int) ($itemArr['variation_id'] ?? 0);
            $meta = $productMeta->get($varId);

            $variationName = $meta ? (string) ($meta->variation_name ?? '') : (string) ($itemArr['variant'] ?? '');
            $variant = $variationName === 'DUMMY' ? '' : $variationName;
            $sku = (string) ($meta->sub_sku ?? $itemArr['sku'] ?? $meta->product_sku ?? '');
            $productName = (string) ($meta->product_name ?? $itemArr['product_name'] ?? '');
            $unit = (string) ($meta->unit ?? $itemArr['unit'] ?? '');

            $stat = $statsByVariation->get($varId);
            $latest = $latestByVariation[$varId] ?? null;

            return [
                'variation_id' => $varId,
                'product_id' => (int) ($meta->product_id ?? $itemArr['product_id'] ?? 0),
                'sku' => $sku,
                'product_name' => $productName,
                'variant' => $variant,
                'unit' => $unit,
                'qty' => (float) ($itemArr['qty'] ?? 0),
                'min_price' => ($canViewCosts && $stat) ? round((float) $stat->min_price, 4) : null,
                'avg_price' => ($canViewCosts && $stat) ? round((float) $stat->avg_price, 4) : null,
                'max_price' => ($canViewCosts && $stat) ? round((float) $stat->max_price, 4) : null,
                'latest_price' => ($canViewCosts && $latest) ? round((float) $latest->latest_price, 4) : null,
                'purchase_count' => $stat ? (int) $stat->purchase_count : 0,
                'latest_date' => $latest ? (string) $latest->latest_date : null,
                'latest_ref_no' => $latest ? (string) ($latest->latest_ref_no ?: ('#' . $latest->latest_transaction_id)) : null,
                'latest_supplier' => $latest ? (string) ($latest->latest_supplier ?? '') : '',
            ];
        })->values();
    }

    public function exportRows(Collection $enrichedLines, bool $canViewCosts = true): array
    {
        return $enrichedLines->map(function (array $row) use ($canViewCosts) {
            return [
                'SKU' => $row['sku'],
                'Product Name' => $row['product_name'],
                'Variant' => $row['variant'],
                'Qty' => (float) $row['qty'],
                'តម្លៃដែលទិញចូលទាបជាងគេ' => $canViewCosts && $row['min_price'] !== null ? round((float) $row['min_price'], 4) : null,
                'តម្លៃមធ្យម' => $canViewCosts && $row['avg_price'] !== null ? round((float) $row['avg_price'], 4) : null,
                'តម្លៃខ្ពស់ជាងគេ' => $canViewCosts && $row['max_price'] !== null ? round((float) $row['max_price'], 4) : null,
                'តម្លៃទិញចូលក្រោយគេ' => $canViewCosts && $row['latest_price'] !== null ? round((float) $row['latest_price'], 4) : null,
            ];
        })->all();
    }

    private function parseNumber(string $raw): float
    {
        $normalized = str_replace([',', ' '], '', trim($raw));
        return is_numeric($normalized) ? max(0.0, (float) $normalized) : 0.0;
    }
}
