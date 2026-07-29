<?php

namespace Modules\SmartStockInventory\Repositories\Enterprise;

use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\Enterprise\SsiAudit;
use Modules\SmartStockInventory\Models\Enterprise\SsiAuditItem;

class SsiAuditRepository
{
    public function nextAuditNo(int $businessId): string
    {
        $prefix = 'SSI-' . now()->format('Ymd') . '-';
        $count = SsiAudit::where('business_id', $businessId)
            ->where('audit_no', 'like', $prefix . '%')
            ->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function createAudit(int $businessId, int $userId, array $data): SsiAudit
    {
        return SsiAudit::create([
            'business_id' => $businessId,
            'location_id' => $data['location_id'] ?? null,
            'audit_no' => $data['audit_no'] ?? $this->nextAuditNo($businessId),
            'name' => $data['name'],
            'audit_type' => $data['audit_type'],
            'count_mode' => $data['count_mode'],
            'status' => $data['scheduled_at'] ? 'scheduled' : 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => $userId,
            'assigned_to' => $data['assigned_to'] ?? null,
            'scope' => $data['scope'] ?? [],
            'settings' => $data['settings'] ?? [],
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function upsertItemFromScan(SsiAudit $audit, array $scanMatch, float $quantity, int $userId, array $locationData = []): SsiAuditItem
    {
        $query = SsiAuditItem::where('business_id', $audit->business_id)
            ->where('audit_id', $audit->id)
            ->where('location_id', $audit->location_id);

        if (! empty($scanMatch['imei'])) {
            $query->where('imei', $scanMatch['imei']);
        } elseif (! empty($scanMatch['serial'])) {
            $query->where('serial', $scanMatch['serial']);
        } elseif (! empty($scanMatch['lot_number'])) {
            $query->where('variation_id', $scanMatch['variation_id'] ?? null)
                ->where('lot_number', $scanMatch['lot_number']);
        } else {
            $query->where('variation_id', $scanMatch['variation_id'] ?? null);
        }

        $item = $query->first();
        if (! $item) {
            $item = new SsiAuditItem([
                'business_id' => $audit->business_id,
                'audit_id' => $audit->id,
                'location_id' => $audit->location_id,
                'product_id' => $scanMatch['product_id'] ?? null,
                'variation_id' => $scanMatch['variation_id'] ?? null,
                'sku' => $scanMatch['sku'] ?? null,
                'product_name' => $scanMatch['product_name'] ?? null,
                'imei' => $scanMatch['imei'] ?? null,
                'serial' => $scanMatch['serial'] ?? null,
                'lot_number' => $scanMatch['lot_number'] ?? null,
                'warehouse' => $locationData['warehouse'] ?? null,
                'zone' => $locationData['zone'] ?? null,
                'rack' => $locationData['rack'] ?? null,
                'shelf' => $locationData['shelf'] ?? null,
                'bin' => $locationData['bin'] ?? null,
                'expected_qty' => $scanMatch['expected_qty'] ?? 0,
                'counted_qty' => 0,
                'verification_status' => 'pending',
            ]);
        }

        $item->counted_qty = (float) $item->counted_qty + $quantity;
        $item->difference_qty = (float) $item->counted_qty - (float) $item->expected_qty;
        $item->mismatch_type = $this->mismatchType((float) $item->difference_qty, $scanMatch);
        $item->counted_by = $userId;
        $item->counted_at = now();
        $item->save();

        return $item;
    }

    public function auditSummary(int $businessId, ?int $locationId = null): array
    {
        $auditQuery = SsiAudit::where('business_id', $businessId);
        $itemQuery = SsiAuditItem::where('business_id', $businessId);
        if ($locationId) {
            $auditQuery->where('location_id', $locationId);
            $itemQuery->where('location_id', $locationId);
        }

        $totalItems = (clone $itemQuery)->count();
        $matched = (clone $itemQuery)->where('difference_qty', 0)->count();

        return [
            'pending_audits' => (clone $auditQuery)->whereIn('status', ['draft', 'scheduled', 'in_progress', 'recount'])->count(),
            'pending_investigations' => DB::table('ssi_investigations')->where('business_id', $businessId)->whereIn('status', ['open', 'investigating'])->count(),
            'accuracy_percent' => $totalItems > 0 ? round(($matched / $totalItems) * 100, 2) : 100,
            'mismatch_items' => (clone $itemQuery)->where('difference_qty', '!=', 0)->count(),
        ];
    }

    private function mismatchType(float $difference, array $scanMatch): ?string
    {
        if (! empty($scanMatch['unmatched'])) {
            return 'extra_imei';
        }
        if ($difference < 0) {
            return 'missing_imei';
        }
        if ($difference > 0) {
            return 'extra_stock';
        }

        return null;
    }
}
