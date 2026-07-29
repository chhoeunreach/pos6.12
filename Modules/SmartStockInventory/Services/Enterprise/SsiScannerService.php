<?php

namespace Modules\SmartStockInventory\Services\Enterprise;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\Enterprise\SsiAudit;
use Modules\SmartStockInventory\Models\Enterprise\SsiAuditScan;
use Modules\SmartStockInventory\Repositories\Enterprise\SsiAuditRepository;

class SsiScannerService
{
    public function __construct(private SsiAuditRepository $audits, private SsiLogService $logs)
    {
    }

    public function scan(SsiAudit $audit, string $scanValue, float $quantity, int $userId, Request $request, array $locationData = []): array
    {
        $normalized = $this->normalize($scanValue);
        $match = $this->resolveScan($audit, $normalized);
        $item = $this->audits->upsertItemFromScan($audit, $match, $quantity, $userId, $locationData);

        $scan = SsiAuditScan::create([
            'business_id' => $audit->business_id,
            'audit_id' => $audit->id,
            'audit_item_id' => $item->id,
            'location_id' => $audit->location_id,
            'scan_type' => $this->scanType($normalized),
            'scan_value' => $scanValue,
            'normalized_value' => $normalized,
            'quantity' => $quantity,
            'warehouse' => $locationData['warehouse'] ?? null,
            'zone' => $locationData['zone'] ?? null,
            'rack' => $locationData['rack'] ?? null,
            'shelf' => $locationData['shelf'] ?? null,
            'bin' => $locationData['bin'] ?? null,
            'device_id' => $request->header('X-Device-Id'),
            'device_name' => $request->header('X-Device-Name'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'scanned_by' => $userId,
            'scanned_at' => now(),
            'metadata' => ['matched' => empty($match['unmatched'])],
        ]);

        $this->logs->log($audit->business_id, 'scan_item', [
            'audit_id' => $audit->id,
            'audit_item_id' => $item->id,
            'subject_type' => 'ssi_audit_scans',
            'subject_id' => $scan->id,
            'log_type' => 'scan',
            'new_values' => $scan->toArray(),
        ], $request);

        return ['item' => $item->fresh(), 'scan' => $scan, 'match' => $match];
    }

    public function resolveScan(SsiAudit $audit, string $normalized): array
    {
        $imei = DB::table('smart_imei_histories as ih')
            ->leftJoin('products as p', 'ih.product_id', '=', 'p.id')
            ->leftJoin('variations as v', 'ih.variation_id', '=', 'v.id')
            ->leftJoin('variation_location_details as vld', function ($join) use ($audit) {
                $join->on('vld.variation_id', '=', 'ih.variation_id');
                if ($audit->location_id) {
                    $join->where('vld.location_id', '=', $audit->location_id);
                }
            })
            ->where('ih.business_id', $audit->business_id)
            ->where('ih.imei', $normalized)
            ->orderByDesc('ih.movement_date')
            ->select('ih.imei', 'ih.product_id', 'ih.variation_id', DB::raw('COALESCE(v.sub_sku, p.sku) as sku'), 'p.name as product_name', DB::raw('COALESCE(vld.qty_available, 0) as expected_qty'))
            ->first();

        if ($imei) {
            return (array) $imei;
        }

        $product = DB::table('variations as v')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->leftJoin('variation_location_details as vld', function ($join) use ($audit) {
                $join->on('vld.variation_id', '=', 'v.id');
                if ($audit->location_id) {
                    $join->where('vld.location_id', '=', $audit->location_id);
                }
            })
            ->where('p.business_id', $audit->business_id)
            ->where(function ($query) use ($normalized) {
                $query->where('v.sub_sku', $normalized)->orWhere('p.sku', $normalized);
            })
            ->select('v.product_id', 'v.id as variation_id', DB::raw('COALESCE(v.sub_sku, p.sku) as sku'), 'p.name as product_name', DB::raw('COALESCE(vld.qty_available, 0) as expected_qty'))
            ->first();

        if ($product) {
            return (array) $product;
        }

        return [
            'unmatched' => true,
            'imei' => $this->scanType($normalized) === 'imei' ? $normalized : null,
            'serial' => $this->scanType($normalized) === 'serial' ? $normalized : null,
            'sku' => $normalized,
            'product_name' => 'Unmatched scan: ' . $normalized,
            'expected_qty' => 0,
        ];
    }

    public function normalize(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', '', $value) ?? $value));
    }

    private function scanType(string $value): string
    {
        if (preg_match('/^\d{14,17}$/', $value)) {
            return 'imei';
        }
        if (preg_match('/^[A-Z0-9\-]{8,}$/', $value)) {
            return 'serial';
        }

        return 'barcode';
    }
}
