<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\LoanManagement\Entities\LoanBusinessLocation;
use Modules\LoanManagement\Entities\LoanProduct;
use Modules\LoanManagement\Entities\LoanProductItem;

class LoanProductController extends Controller
{
    protected string $connection = 'mysql_loan';

    public function index(Request $request)
    {
        $query = LoanProduct::query();

        // Search keyword
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('imei', 'like', "%{$search}%")
                  ->orWhere('meta_json', 'like', "%{$search}%");
            });
        }

        // Filter by location
        if ($request->filled('location_id')) {
            $query->where('loan_business_location_id', (int) $request->input('location_id'));
        }

        // Filter by Category
        if ($request->filled('category')) {
            $cat = trim((string) $request->input('category'));
            $query->where('meta_json->category', $cat);
        }

        // Filter by Brand
        if ($request->filled('brand')) {
            $br = trim((string) $request->input('brand'));
            $query->where('meta_json->brand', $br);
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', (float) $request->input('max_price'));
        }

        // Filter by stock status
        $stockStatus = $request->input('stock_status');
        if ($stockStatus === 'in_stock') {
            $query->where('qty_available', '>', 0);
        } elseif ($stockStatus === 'low_stock') {
            $query->where('qty_available', '>', 0)->where('qty_available', '<=', 5);
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where(function ($q) {
                $q->whereNull('qty_available')->orWhere('qty_available', '<=', 0);
            });
        }

        // Sorting
        $sort = (string) $request->input('sort', 'newest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('id', 'asc');
                break;
            case 'price_asc':
                $query->orderBy('selling_price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('selling_price', 'desc');
                break;
            case 'stock_desc':
                $query->orderBy('qty_available', 'desc');
                break;
            case 'stock_asc':
                $query->orderBy('qty_available', 'asc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'newest':
            default:
                $query->orderByDesc('id');
                break;
        }

        // Summary counts across whole catalog
        $totalProducts = LoanProduct::count();
        $inStockCount = LoanProduct::where('qty_available', '>', 5)->count();
        $lowStockCount = LoanProduct::where('qty_available', '>', 0)->where('qty_available', '<=', 5)->count();
        $outOfStockCount = LoanProduct::where(function ($q) {
            $q->whereNull('qty_available')->orWhere('qty_available', '<=', 0);
        })->count();
        $totalStockQty = (int) LoanProduct::sum('qty_available');
        $totalCostValue = (float) LoanProduct::sum(DB::raw('COALESCE(cost_price, 0) * COALESCE(qty_available, 0)'));
        $totalRetailValue = (float) LoanProduct::sum(DB::raw('COALESCE(selling_price, 0) * COALESCE(qty_available, 0)'));
        $perPage = (int) $request->input('per_page', 250);
        if ($perPage <= 0 || $perPage > 1000) {
            $perPage = 250;
        }

        $products = $query->paginate($perPage)->appends($request->query());

        // Extract unique Categories & Brands for filter dropdowns
        $allProductsMeta = LoanProduct::select('meta_json')->get();
        $categories = collect();
        $brands = collect();
        foreach ($allProductsMeta as $pMeta) {
            $meta = is_array($pMeta->meta_json) ? $pMeta->meta_json : json_decode((string) $pMeta->meta_json, true);
            if (! empty($meta['category'])) {
                $categories->push(trim($meta['category']));
            }
            if (! empty($meta['brand'])) {
                $brands->push(trim($meta['brand']));
            }
        }
        $categories = $categories->unique()->filter()->sort()->values();
        $brands = $brands->unique()->filter()->sort()->values();

        // Locations
        $locations = collect();
        if (Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            $locations = LoanBusinessLocation::orderBy('name')->get();
        }

        return view('loanmanagement::products.index', compact(
            'products',
            'locations',
            'categories',
            'brands',
            'totalProducts',
            'inStockCount',
            'lowStockCount',
            'outOfStockCount',
            'totalStockQty',
            'totalCostValue',
            'totalRetailValue'
        ));
    }

    public function create()
    {
        $locations = collect();
        if (Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            $locations = LoanBusinessLocation::orderBy('name')->get();
        }

        $suggestedSku = 'INS-' . strtoupper(substr(uniqid(), -6));

        return view('loanmanagement::products.create', compact('locations', 'suggestedSku'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'imei' => 'nullable|string|max:100',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'qty_available' => 'nullable|integer|min:0',
            'loan_business_location_id' => 'nullable|integer',
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'min_down_payment_percent' => 'nullable|numeric|min:0|max:100',
            'allowed_durations' => 'nullable|array',
            'description' => 'nullable|string|max:2000',
            'product_image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
            'serial_numbers' => 'nullable|string',
        ]);

        $imagePath = null;
        if ($request->hasFile('product_image')) {
            $file = $request->file('product_image');
            $imagePath = $file->store('loan-products', 'public');
        }

        $metaJson = [
            'brand' => $request->input('brand'),
            'category' => $request->input('category'),
            'min_down_payment_percent' => (float) $request->input('min_down_payment_percent', 0),
            'allowed_durations' => $request->input('allowed_durations', [3, 6, 12, 24]),
            'description' => $request->input('description'),
            'image_path' => $imagePath,
            'color' => $request->input('color'),
            'storage' => $request->input('storage'),
        ];

        $product = LoanProduct::create([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?: ('INS-' . strtoupper(substr(uniqid(), -6))),
            'imei' => $validated['imei'] ?? null,
            'selling_price' => (float) $validated['selling_price'],
            'cost_price' => (float) ($validated['cost_price'] ?? 0),
            'qty_available' => (int) ($validated['qty_available'] ?? 1),
            'loan_business_location_id' => $validated['loan_business_location_id'] ?: null,
            'meta_json' => $metaJson,
        ]);

        // Process individual serials / IMEIs if provided
        $serialsText = trim((string) $request->input('serial_numbers', ''));
        if ($serialsText !== '' && Schema::connection($this->connection)->hasTable('loan_product_items')) {
            $lines = preg_split('/[\r\n,]+/', $serialsText);
            foreach ($lines as $line) {
                $serial = trim($line);
                if ($serial !== '') {
                    LoanProductItem::create([
                        'loan_product_id' => $product->id,
                        'serial_no' => $serial,
                        'imei' => $serial,
                        'status' => 'available',
                    ]);
                }
            }
        }

        return redirect()->route('loan-management.products.index')
            ->with('status', "Installment product '{$product->name}' created successfully!");
    }

    public function show(int $id)
    {
        $product = LoanProduct::with(['location', 'items'])->findOrFail($id);

        // Find recent loans containing this product name or linked in loan_items
        $recentLoans = collect();
        if (Schema::connection($this->connection)->hasTable('loans')) {
            $loanIds = collect();
            if (Schema::connection($this->connection)->hasTable('loan_items')) {
                $loanIds = DB::connection($this->connection)->table('loan_items')
                    ->where('loan_product_id', $product->id)
                    ->pluck('loan_id');
            }

            $recentLoans = DB::connection($this->connection)->table('loans')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($product, $loanIds) {
                    if ($loanIds->isNotEmpty()) {
                        $q->whereIn('id', $loanIds)
                          ->orWhere('product_name_snapshot', 'like', "%{$product->name}%");
                    } else {
                        $q->where('product_name_snapshot', 'like', "%{$product->name}%");
                    }
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        return view('loanmanagement::products.show', compact('product', 'recentLoans'));
    }

    public function edit(int $id)
    {
        $product = LoanProduct::findOrFail($id);
        $locations = collect();
        if (Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            $locations = LoanBusinessLocation::orderBy('name')->get();
        }

        return view('loanmanagement::products.edit', compact('product', 'locations'));
    }

    public function update(Request $request, int $id)
    {
        $product = LoanProduct::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'imei' => 'nullable|string|max:100',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'qty_available' => 'nullable|integer|min:0',
            'loan_business_location_id' => 'nullable|integer',
            'brand' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'min_down_payment_percent' => 'nullable|numeric|min:0|max:100',
            'allowed_durations' => 'nullable|array',
            'description' => 'nullable|string|max:2000',
            'product_image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
        ]);

        $metaJson = is_array($product->meta_json) ? $product->meta_json : (json_decode((string) $product->meta_json, true) ?: []);

        if ($request->hasFile('product_image')) {
            if (! empty($metaJson['image_path']) && Storage::disk('public')->exists($metaJson['image_path'])) {
                Storage::disk('public')->delete($metaJson['image_path']);
            }
            $file = $request->file('product_image');
            $metaJson['image_path'] = $file->store('loan-products', 'public');
        } elseif ($request->boolean('remove_image')) {
            if (! empty($metaJson['image_path']) && Storage::disk('public')->exists($metaJson['image_path'])) {
                Storage::disk('public')->delete($metaJson['image_path']);
            }
            $metaJson['image_path'] = null;
        }

        $metaJson['brand'] = $request->input('brand');
        $metaJson['category'] = $request->input('category');
        $metaJson['min_down_payment_percent'] = (float) $request->input('min_down_payment_percent', 0);
        $metaJson['allowed_durations'] = $request->input('allowed_durations', [3, 6, 12, 24]);
        $metaJson['description'] = $request->input('description');
        $metaJson['color'] = $request->input('color');
        $metaJson['storage'] = $request->input('storage');

        $product->update([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?: $product->sku,
            'imei' => $validated['imei'] ?? null,
            'selling_price' => (float) $validated['selling_price'],
            'cost_price' => (float) ($validated['cost_price'] ?? 0),
            'qty_available' => (int) ($validated['qty_available'] ?? 0),
            'loan_business_location_id' => $validated['loan_business_location_id'] ?: null,
            'meta_json' => $metaJson,
        ]);

        // Process individual serials / IMEIs if provided on edit
        $serialsText = trim((string) $request->input('serial_numbers', ''));
        if ($serialsText !== '' && Schema::connection($this->connection)->hasTable('loan_product_items')) {
            $lines = preg_split('/[\r\n,]+/', $serialsText);
            foreach ($lines as $line) {
                $serial = trim($line);
                if ($serial !== '') {
                    LoanProductItem::create([
                        'loan_product_id' => $product->id,
                        'serial_no' => $serial,
                        'imei' => $serial,
                        'status' => 'available',
                    ]);
                }
            }
        }

        return redirect()->route('loan-management.products.index')
            ->with('status', "Installment product '{$product->name}' updated successfully!");
    }

    public function destroy(int $id)
    {
        $product = LoanProduct::findOrFail($id);
        $name = $product->name;

        // Check if there are active loans
        $hasActiveLoans = false;
        if (Schema::connection($this->connection)->hasTable('loans')) {
            $loanIds = collect();
            if (Schema::connection($this->connection)->hasTable('loan_items')) {
                $loanIds = DB::connection($this->connection)->table('loan_items')
                    ->where('loan_product_id', $product->id)
                    ->pluck('loan_id');
            }

            $hasActiveLoans = DB::connection($this->connection)->table('loans')
                ->whereIn('status', ['active', 'in_progress', 'approved', 'pending'])
                ->whereNull('deleted_at')
                ->where(function ($q) use ($product, $loanIds) {
                    if ($loanIds->isNotEmpty()) {
                        $q->whereIn('id', $loanIds)
                          ->orWhere('product_name_snapshot', 'like', "%{$product->name}%");
                    } else {
                        $q->where('product_name_snapshot', 'like', "%{$product->name}%");
                    }
                })
                ->exists();
        }

        if ($hasActiveLoans) {
            return back()->withErrors(['error' => "Cannot delete '{$name}' because it is linked to active loans. You can set available quantity to 0 instead."]);
        }

        $product->delete();

        return redirect()->route('loan-management.products.index')
            ->with('status', "Installment product '{$name}' deleted successfully.");
    }

    public function quickStockAdjust(Request $request, int $id)
    {
        $product = LoanProduct::findOrFail($id);

        if ($request->has('set_qty')) {
            $newQty = max(0, (int) $request->input('set_qty'));
        } else {
            $change = (int) $request->input('change_qty', 0);
            $newQty = max(0, (int) $product->qty_available + $change);
        }

        $product->update(['qty_available' => $newQty]);

        $statusClass = 'in-stock';
        $statusText = "{$newQty} units";
        if ($newQty === 0) {
            $statusClass = 'out-of-stock';
            $statusText = '0 Out';
        } elseif ($newQty <= 5) {
            $statusClass = 'low-stock';
            $statusText = "{$newQty} low";
        }

        return response()->json([
            'success' => true,
            'new_qty' => $newQty,
            'status_class' => $statusClass,
            'status_text' => $statusText,
            'message' => "Stock for '{$product->name}' updated to {$newQty}",
        ]);
    }

    public function bulkAction(Request $request)
    {
        $action = (string) $request->input('bulk_action');
        $ids = (array) $request->input('selected_ids', []);

        if (empty($ids)) {
            return back()->withErrors(['error' => 'Please select at least one product.']);
        }

        $products = LoanProduct::whereIn('id', $ids)->get();

        if ($action === 'delete') {
            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($products as $p) {
                $hasActiveLoans = false;
                if (Schema::connection($this->connection)->hasTable('loans')) {
                    $loanIds = collect();
                    if (Schema::connection($this->connection)->hasTable('loan_items')) {
                        $loanIds = DB::connection($this->connection)->table('loan_items')
                            ->where('loan_product_id', $p->id)
                            ->pluck('loan_id');
                    }

                    $hasActiveLoans = DB::connection($this->connection)->table('loans')
                        ->whereIn('status', ['active', 'in_progress', 'approved', 'pending'])
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($p, $loanIds) {
                            if ($loanIds->isNotEmpty()) {
                                $q->whereIn('id', $loanIds)
                                  ->orWhere('product_name_snapshot', 'like', "%{$p->name}%");
                            } else {
                                $q->where('product_name_snapshot', 'like', "%{$p->name}%");
                            }
                        })
                        ->exists();
                }

                if (! $hasActiveLoans) {
                    $p->delete();
                    $deletedCount++;
                } else {
                    $skippedCount++;
                }
            }

            $msg = "Successfully deleted {$deletedCount} product(s).";
            if ($skippedCount > 0) {
                $msg .= " ({$skippedCount} skipped due to active loans).";
            }

            return redirect()->route('loan-management.products.index')->with('status', $msg);
        }

        if ($action === 'assign_location') {
            $locationId = $request->input('bulk_location_id') ?: null;
            LoanProduct::whereIn('id', $ids)->update(['loan_business_location_id' => $locationId]);
            return redirect()->route('loan-management.products.index')->with('status', 'Location updated for ' . count($ids) . ' product(s).');
        }

        if ($action === 'stock_in_stock') {
            LoanProduct::whereIn('id', $ids)->where('qty_available', 0)->update(['qty_available' => 1]);
            return redirect()->route('loan-management.products.index')->with('status', 'Stock status updated for selected products.');
        }

        return back()->withErrors(['error' => 'Invalid bulk action specified.']);
    }

    public function exportCsv(Request $request)
    {
        $query = LoanProduct::with('location')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('imei', 'like', "%{$search}%");
            });
        }
        if ($request->filled('location_id')) {
            $query->where('loan_business_location_id', (int) $request->input('location_id'));
        }
        if ($request->filled('category')) {
            $query->where('meta_json->category', trim((string) $request->input('category')));
        }
        if ($request->filled('brand')) {
            $query->where('meta_json->brand', trim((string) $request->input('brand')));
        }
        $stockStatus = $request->input('stock_status');
        if ($stockStatus === 'in_stock') {
            $query->where('qty_available', '>', 0);
        } elseif ($stockStatus === 'low_stock') {
            $query->where('qty_available', '>', 0)->where('qty_available', '<=', 5);
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where(function ($q) {
                $q->whereNull('qty_available')->orWhere('qty_available', '<=', 0);
            });
        }

        $products = $query->get();
        $filename = 'installment_products_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for proper Excel rendering (especially Khmer script)
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Product Name',
                'SKU',
                'IMEI / Serial',
                'Brand',
                'Category',
                'Selling Price (USD)',
                'Cost Price (USD)',
                'Profit Margin (USD)',
                'Available Qty',
                'Location',
                'Min Down Payment %',
                'Created Date',
            ]);

            foreach ($products as $p) {
                $selling = (float) $p->selling_price;
                $cost = (float) ($p->cost_price ?? 0);
                $margin = $selling - $cost;

                fputcsv($handle, [
                    $p->id,
                    $p->name,
                    $p->sku ?: '',
                    $p->imei ?: '',
                    $p->brand ?: '',
                    $p->category ?: '',
                    number_format($selling, 2, '.', ''),
                    number_format($cost, 2, '.', ''),
                    number_format($margin, 2, '.', ''),
                    (int) ($p->qty_available ?? 0),
                    $p->location->name ?? 'All Branches',
                    $p->min_down_payment_percent ?: '0',
                    $p->created_at ? $p->created_at->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function calculatorData(int $id)
    {
        $product = LoanProduct::findOrFail($id);
        $meta = is_array($product->meta_json) ? $product->meta_json : (json_decode((string) $product->meta_json, true) ?: []);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'selling_price' => (float) $product->selling_price,
            'cost_price' => (float) ($product->cost_price ?? 0),
            'qty_available' => (int) ($product->qty_available ?? 0),
            'min_down_payment_percent' => (float) ($meta['min_down_payment_percent'] ?? 0),
            'allowed_durations' => $meta['allowed_durations'] ?? [3, 6, 12, 24],
            'image_url' => $product->image_url,
            'brand' => $product->brand,
            'category' => $product->category,
            'create_loan_url' => route('loan-management.loans.create', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'principal_amount' => $product->selling_price,
            ]),
        ]);
    }

    public function ajaxSearch(Request $request)
    {
        $term = trim((string) $request->input('q', $request->input('term', '')));
        $query = LoanProduct::query()->orderByDesc('id');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhere('imei', 'like', "%{$term}%");
            });
        }

        $results = $query->limit(20)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'text' => $p->name . ' (' . $p->sku . ') - $' . number_format($p->selling_price, 2),
                'name' => $p->name,
                'sku' => $p->sku,
                'imei' => $p->imei,
                'price' => $p->selling_price,
                'cost_price' => $p->cost_price,
                'qty_available' => $p->qty_available,
                'image_url' => $p->image_url,
                'min_down_payment_percent' => $p->min_down_payment_percent,
            ];
        });

        return response()->json(['results' => $results]);
    }
}
