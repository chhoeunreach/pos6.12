<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartStockActionLog;
use Modules\SmartStockInventory\Models\SmartStockPriceCompare;
use Modules\SmartStockInventory\Models\SmartStockPriceCompareLine;
use Modules\SmartStockInventory\Services\PriceCompareService;
use Modules\SmartStockInventory\Support\SkuPermissions;

class PriceCompareController extends BaseSmartStockController
{
    public function index(Request $request, PriceCompareService $service)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $locations = $this->locationOptions($businessId)->whereIn('id', $permittedLocationIds)->values();

        if ($request->ajax()) {
            $query = DB::table('smart_stock_price_compares as pc')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 'pc.location_id')
                ->leftJoin('users as u', 'u.id', '=', 'pc.created_by')
                ->leftJoin('smart_stock_price_compare_lines as pcl', 'pcl.price_compare_id', '=', 'pc.id')
                ->where('pc.business_id', $businessId)
                ->whereNull('pc.deleted_at')
                ->where(function ($q) use ($permittedLocationIds) {
                    $q->whereNull('pc.location_id')
                        ->orWhereIn('pc.location_id', $permittedLocationIds ?: [-1]);
                });

            if ($request->filled('location_id') && $request->input('location_id') !== 'all') {
                $locId = (int) $request->input('location_id');
                if ($locId > 0) {
                    abort_unless(in_array($locId, $permittedLocationIds, true), 403);
                    $query->where('pc.location_id', $locId);
                }
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereDate('pc.compare_date', '>=', (string) $request->input('start_date'))
                    ->whereDate('pc.compare_date', '<=', (string) $request->input('end_date'));
            }

            $records = $query
                ->groupBy('pc.id', 'pc.ref_no', 'pc.title', 'pc.compare_date', 'pc.location_id', 'bl.name', 'u.first_name', 'u.last_name', 'u.username', 'pc.notes', 'pc.created_at')
                ->selectRaw('
                    pc.id,
                    pc.ref_no,
                    pc.title,
                    pc.compare_date,
                    pc.location_id,
                    bl.name as location_name,
                    TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) as created_by_full,
                    u.username as created_by_username,
                    pc.notes,
                    COUNT(pcl.id) as item_count,
                    COALESCE(SUM(pcl.qty), 0) as total_qty
                ')
                ->orderByDesc('pc.compare_date')
                ->orderByDesc('pc.id')
                ->get();

            $user = $request->user();
            $canUpdate = $this->canManage($user, 'stock_inventory.update');
            $canDelete = $this->canManage($user, 'stock_inventory.delete');
            $canExport = $this->canManage($user, 'stock_inventory.export');

            $rows = $records->map(function ($row) use ($canUpdate, $canDelete, $canExport) {
                $showUrl = ssi_route('ssi.compare_price.show', ['id' => $row->id]);
                $editUrl = ssi_route('ssi.compare_price.edit', ['id' => $row->id]);
                $exportUrl = ssi_route('ssi.compare_price.export', ['id' => $row->id]);
                $deleteUrl = ssi_route('ssi.compare_price.destroy', ['id' => $row->id]);

                $actions = '<div class="btn-group btn-group-xs">';
                $actions .= '<a href="' . e($showUrl) . '" class="btn btn-info" title="View"><i class="fa fa-eye"></i> View</a>';
                if ($canExport) {
                    $actions .= '<a href="' . e($exportUrl) . '" class="btn btn-success" title="Export Excel"><i class="fa fa-file-excel-o"></i> Excel</a>';
                }
                if ($canUpdate) {
                    $actions .= '<a href="' . e($editUrl) . '" class="btn btn-primary" title="Edit"><i class="fa fa-edit"></i> Edit</a>';
                }
                if ($canDelete) {
                    $actions .= '<button type="button" class="btn btn-danger js-delete-compare-price" data-url="' . e($deleteUrl) . '" data-ref="' . e($row->ref_no) . '" title="Delete"><i class="fa fa-trash"></i></button>';
                }
                $actions .= '</div>';

                $createdBy = trim((string) $row->created_by_full) !== '' ? trim((string) $row->created_by_full) : (string) ($row->created_by_username ?? '');

                return [
                    'compare_date' => (string) $row->compare_date,
                    'ref_no' => '<a href="' . e($showUrl) . '"><strong>' . e($row->ref_no) . '</strong></a>',
                    'title' => e((string) ($row->title ?: '—')),
                    'location_name' => e((string) ($row->location_name ?: 'All Permitted Locations')),
                    'item_count' => (int) $row->item_count,
                    'total_qty' => (float) $row->total_qty,
                    'created_by' => e($createdBy),
                    'notes' => e((string) ($row->notes ?? '')),
                    'actions' => $actions,
                ];
            })->values()->all();

            return response()->json(['data' => $rows]);
        }

        return view('smartstockinventory::compare_price.index', compact('locations'));
    }

    public function create(Request $request, PriceCompareService $service)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $locations = $this->locationOptions($businessId)->whereIn('id', $permittedLocationIds)->values();
        $compare = new SmartStockPriceCompare([
            'ref_no' => $service->generateRefNo($businessId),
            'compare_date' => now()->format('Y-m-d'),
            'title' => 'Price Compare ' . now()->format('Y-m-d'),
        ]);
        $enrichedLines = collect();
        $canViewCosts = $this->canViewCosts($request->user());

        return view('smartstockinventory::compare_price.form', [
            'compare' => $compare,
            'locations' => $locations,
            'enrichedLines' => $enrichedLines,
            'canViewCosts' => $canViewCosts,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request, PriceCompareService $service)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);

        $data = $this->validatedPayload($request, $permittedLocationIds);
        $resolved = $service->resolveSkus($businessId, $data['items']);
        if (! empty($resolved['missing'])) {
            $missingSkus = implode(', ', array_column($resolved['missing'], 'sku'));
            throw ValidationException::withMessages(['items' => 'SKU not found in products: ' . $missingSkus]);
        }
        if (empty($resolved['matched'])) {
            throw ValidationException::withMessages(['items' => 'Please add or import at least one valid SKU and Qty.']);
        }

        $compare = DB::transaction(function () use ($businessId, $data, $resolved, $service) {
            $refNo = trim((string) ($data['ref_no'] ?? ''));
            if ($refNo === '') {
                $refNo = $service->generateRefNo($businessId);
            }

            $compare = SmartStockPriceCompare::create([
                'business_id' => $businessId,
                'location_id' => $data['location_id'],
                'ref_no' => $refNo,
                'title' => $data['title'],
                'compare_date' => $data['compare_date'],
                'notes' => $data['notes'],
                'created_by' => auth()->id(),
            ]);

            foreach ($resolved['matched'] as $index => $line) {
                SmartStockPriceCompareLine::create([
                    'price_compare_id' => $compare->id,
                    'business_id' => $businessId,
                    'product_id' => $line['product_id'],
                    'variation_id' => $line['variation_id'],
                    'sku' => $line['sku'],
                    'qty' => $line['qty'],
                    'sort_order' => $index + 1,
                ]);
            }

            return $compare;
        });

        $this->logAction('create_price_compare', $compare->location_id, $compare->id, null, $compare->toArray(), 'Created price compare sheet');

        if ($request->input('submit_action') === 'save_and_export' && $this->canManage($request->user(), 'stock_inventory.export')) {
            return redirect()->to(ssi_route('ssi.compare_price.export', ['id' => $compare->id]));
        }

        return redirect()
            ->to(ssi_route('ssi.compare_price.show', ['id' => $compare->id]))
            ->with('status', ['success' => 1, 'msg' => 'Price Compare saved (' . $compare->ref_no . ').']);
    }

    public function show(Request $request, PriceCompareService $service, int $id)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);

        $compare = $this->findScopedCompare($businessId, $permittedLocationIds, $id);
        $canViewCosts = $this->canViewCosts($request->user());
        $enrichedLines = $service->enrichWithRealPurchasePrices(
            $businessId,
            $compare->lines,
            $permittedLocationIds,
            $compare->location_id,
            $canViewCosts
        );

        $locationName = $compare->location_id
            ? (string) (DB::table('business_locations')->where('id', $compare->location_id)->value('name') ?: 'Location #' . $compare->location_id)
            : 'All Permitted Locations';

        return view('smartstockinventory::compare_price.show', compact('compare', 'enrichedLines', 'canViewCosts', 'locationName'));
    }

    public function edit(Request $request, PriceCompareService $service, int $id)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $locations = $this->locationOptions($businessId)->whereIn('id', $permittedLocationIds)->values();

        $compare = $this->findScopedCompare($businessId, $permittedLocationIds, $id);
        $canViewCosts = $this->canViewCosts($request->user());
        $enrichedLines = $service->enrichWithRealPurchasePrices(
            $businessId,
            $compare->lines,
            $permittedLocationIds,
            $compare->location_id,
            $canViewCosts
        );

        return view('smartstockinventory::compare_price.form', [
            'compare' => $compare,
            'locations' => $locations,
            'enrichedLines' => $enrichedLines,
            'canViewCosts' => $canViewCosts,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, PriceCompareService $service, int $id)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $compare = $this->findScopedCompare($businessId, $permittedLocationIds, $id);

        $data = $this->validatedPayload($request, $permittedLocationIds, $compare->id);
        $resolved = $service->resolveSkus($businessId, $data['items']);
        if (! empty($resolved['missing'])) {
            $missingSkus = implode(', ', array_column($resolved['missing'], 'sku'));
            throw ValidationException::withMessages(['items' => 'SKU not found in products: ' . $missingSkus]);
        }
        if (empty($resolved['matched'])) {
            throw ValidationException::withMessages(['items' => 'Please add or import at least one valid SKU and Qty.']);
        }

        $old = $compare->load('lines')->toArray();

        DB::transaction(function () use ($compare, $businessId, $data, $resolved) {
            $compare->update([
                'location_id' => $data['location_id'],
                'ref_no' => trim((string) ($data['ref_no'] ?: $compare->ref_no)),
                'title' => $data['title'],
                'compare_date' => $data['compare_date'],
                'notes' => $data['notes'],
            ]);

            SmartStockPriceCompareLine::where('price_compare_id', $compare->id)->delete();

            foreach ($resolved['matched'] as $index => $line) {
                SmartStockPriceCompareLine::create([
                    'price_compare_id' => $compare->id,
                    'business_id' => $businessId,
                    'product_id' => $line['product_id'],
                    'variation_id' => $line['variation_id'],
                    'sku' => $line['sku'],
                    'qty' => $line['qty'],
                    'sort_order' => $index + 1,
                ]);
            }
        });

        $this->logAction('update_price_compare', $compare->location_id, $compare->id, $old, $compare->fresh('lines')->toArray(), 'Updated price compare sheet');

        if ($request->input('submit_action') === 'save_and_export' && $this->canManage($request->user(), 'stock_inventory.export')) {
            return redirect()->to(ssi_route('ssi.compare_price.export', ['id' => $compare->id]));
        }

        return redirect()
            ->to(ssi_route('ssi.compare_price.show', ['id' => $compare->id]))
            ->with('status', ['success' => 1, 'msg' => 'Price Compare updated (' . $compare->ref_no . ').']);
    }

    public function destroy(Request $request, PriceCompareService $service, int $id)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $compare = $this->findScopedCompare($businessId, $permittedLocationIds, $id);

        $old = $compare->load('lines')->toArray();
        $compare->delete();
        $this->logAction('delete_price_compare', $compare->location_id, $compare->id, $old, null, 'Deleted price compare sheet');

        if ($request->ajax()) {
            return response()->json(['success' => true, 'msg' => 'Price Compare deleted.']);
        }

        return redirect()
            ->to(ssi_route('ssi.compare_price.index'))
            ->with('status', ['success' => 1, 'msg' => 'Price Compare deleted.']);
    }

    public function export(Request $request, PriceCompareService $service, int $id)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $compare = $this->findScopedCompare($businessId, $permittedLocationIds, $id);

        $canViewCosts = $this->canViewCosts($request->user());
        $enriched = $service->enrichWithRealPurchasePrices(
            $businessId,
            $compare->lines,
            $permittedLocationIds,
            $compare->location_id,
            $canViewCosts
        );

        $rows = $service->exportRows($enriched, $canViewCosts);
        $safeRef = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $compare->ref_no) ?: 'compare_price';

        return Excel::download(new ArrayExport($rows), $safeRef . '_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function template()
    {
        $rows = [
            ['sku' => 'SAMPLE-SKU-001', 'qty' => 5],
            ['sku' => 'SAMPLE-SKU-002', 'qty' => 10],
        ];

        return Excel::download(new ArrayExport($rows), 'compare_price_import_template.xlsx');
    }

    public function importPreview(Request $request, PriceCompareService $service)
    {
        $service->ensureTablesExist();
        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);

        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        if ($locationId !== null && $locationId > 0) {
            abort_unless(in_array($locationId, $permittedLocationIds, true), 403);
        } else {
            $locationId = null;
        }

        $rawItems = [];
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);
            $rawItems = $service->parseUploadedFile($request->file('file'));
        } elseif ($request->filled('paste_text')) {
            $rawItems = $service->parsePastedText((string) $request->input('paste_text'));
        } elseif (is_array($request->input('items'))) {
            $rawItems = $request->input('items');
        }

        $resolved = $service->resolveSkus($businessId, $rawItems);
        $canViewCosts = $this->canViewCosts($request->user());
        $enriched = $service->enrichWithRealPurchasePrices(
            $businessId,
            $resolved['matched'],
            $permittedLocationIds,
            $locationId,
            $canViewCosts
        );

        return response()->json([
            'matched' => $enriched->values()->all(),
            'missing' => $resolved['missing'],
        ]);
    }

    public function searchSku(Request $request)
    {
        $businessId = $this->businessId();
        $term = trim((string) $request->input('q', ''));
        if ($term === '') {
            return response()->json(['results' => []]);
        }

        $like = '%' . $term . '%';
        $items = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)
            ->whereNull('v.deleted_at')
            ->where(function ($q) use ($like) {
                $q->where('v.sub_sku', 'like', $like)
                    ->orWhere('p.sku', 'like', $like)
                    ->orWhere('p.name', 'like', $like)
                    ->orWhere('v.name', 'like', $like);
            })
            ->orderBy('p.name')
            ->orderBy('v.id')
            ->limit(25)
            ->get([
                'v.id as variation_id',
                'v.sub_sku',
                'p.sku as product_sku',
                'p.name as product_name',
                'v.name as variation_name',
            ]);

        $results = $items->map(function ($row) {
            $sku = (string) ($row->sub_sku ?: $row->product_sku);
            $variant = $row->variation_name && $row->variation_name !== 'DUMMY' ? ' (' . $row->variation_name . ')' : '';

            return [
                'id' => $sku,
                'text' => $sku . ' — ' . $row->product_name . $variant,
                'sku' => $sku,
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }

    private function findScopedCompare(int $businessId, array $permittedLocationIds, int $id): SmartStockPriceCompare
    {
        $compare = SmartStockPriceCompare::with('lines')
            ->where('business_id', $businessId)
            ->findOrFail($id);

        if ($compare->location_id !== null) {
            abort_unless(in_array((int) $compare->location_id, $permittedLocationIds, true), 403);
        }

        return $compare;
    }

    private function validatedPayload(Request $request, array $permittedLocationIds, ?int $ignoreId = null): array
    {
        $businessId = $this->businessId();
        $validated = $request->validate([
            'location_id' => 'nullable|integer|min:1',
            'ref_no' => 'nullable|string|max:60',
            'title' => 'nullable|string|max:191',
            'compare_date' => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.sku' => 'required|string|max:191',
            'items.*.qty' => 'nullable|numeric|min:0',
        ]);

        $locationId = ! empty($validated['location_id']) ? (int) $validated['location_id'] : null;
        if ($locationId !== null) {
            abort_unless(in_array($locationId, $permittedLocationIds, true), 403);
        }

        $refNo = trim((string) ($validated['ref_no'] ?? ''));
        if ($refNo !== '') {
            $duplicate = SmartStockPriceCompare::withTrashed()
                ->where('business_id', $businessId)
                ->where('ref_no', $refNo)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['ref_no' => 'Reference number already exists.']);
            }
        }

        return [
            'location_id' => $locationId,
            'ref_no' => $refNo,
            'title' => trim((string) ($validated['title'] ?? '')) ?: null,
            'compare_date' => $validated['compare_date'],
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'items' => $validated['items'] ?? [],
        ];
    }

    private function canViewCosts($user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperAdminOrAdmin($user)) {
            return true;
        }

        return SkuPermissions::allows($user, 'view_cost')
            || $user->can('view_purchase_price')
            || $user->can('purchase.view')
            || $user->can('purchase.create');
    }

    private function canManage($user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isSuperAdminOrAdmin($user) || $user->can($permission);
    }

    private function logAction(string $actionType, ?int $locationId, int $recordId, $old, $new, string $reason): void
    {
        $user = $this->currentUser();
        $userName = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
        if ($userName === '') {
            $userName = (string) ($user->username ?? '');
        }

        SmartStockActionLog::create([
            'user_id' => $user->id ?? auth()->id(),
            'user_name' => $userName,
            'business_id' => $this->businessId(),
            'module_name' => 'SmartStockInventory',
            'table_name' => 'smart_stock_price_compares',
            'record_id' => $recordId,
            'location_id' => $locationId,
            'action_type' => $actionType,
            'reference_type' => 'price_compare',
            'reference_id' => $recordId,
            'old_data' => $old ? json_encode($old) : null,
            'new_data' => $new ? json_encode($new) : null,
            'reason' => $reason,
            'ip_address' => request()->ip(),
        ]);
    }
}
