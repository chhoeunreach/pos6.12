<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Requests\MobileApi\StoreProductRequest;
use App\Http\Requests\MobileApi\UpdateProductRequest;
use App\Http\Resources\Mobile\ProductResource;
use App\Product;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @group Products
 * Product management
 */
class ProductController extends BaseController
{
    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');

        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('Invalid location');
        }

        $query = Product::where('products.business_id', $business_id)
            ->active()
            ->with(['variations.product_variation', 'variations.variation_location_details', 'brand', 'category', 'unit', 'product_locations']);

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->filled('brand_id')) {
            $query->where('products.brand_id', $request->brand_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }
        if ($request->filled('type')) {
            $query->where('products.type', $request->type);
        }
        if ($location_id) {
            $query->forLocation($location_id);
        }

        $perPage = $request->input('per_page', 20);
        $products = $query->orderBy('products.name')->paginate($perPage);

        return $this->success(ProductResource::collection($products));
    }

    public function show($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');

        $product = Product::where('business_id', $business_id)
            ->with(['variations.product_variation', 'variations.variation_location_details', 'brand', 'category', 'unit', 'product_locations'])
            ->findOrFail($id);

        return $this->success(new ProductResource($product));
    }

    public function store(StoreProductRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        try {
            DB::beginTransaction();

            $product_data = $request->validated();
            $product_data['business_id'] = $business_id;
            $product_data['created_by'] = $user_id;
            $product_data['sku'] = $product_data['sku'] ?? $product_data['sku_manual'] ?? '';

            if (empty($product_data['sku'])) {
                $ref_count = $this->getReferenceCount('products');
                $product_data['sku'] = $this->generateReferenceNumber('products', $ref_count);
            }

            $product = Product::create($product_data);

            if (!empty($product_data['product_locations'])) {
                $product->product_locations()->sync($product_data['product_locations']);
            }

            $variation = $this->createSingleVariation($product, $product_data);

            DB::commit();

            $product->load(['variations', 'brand', 'category', 'unit']);

            return $this->success(new ProductResource($product), 'Product created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    public function update($id, UpdateProductRequest $request)
    {
        $business_id = $this->getBusinessId();

        $product = Product::where('business_id', $business_id)->findOrFail($id);

        $product->update($request->validated());

        if ($request->has('product_locations')) {
            $product->product_locations()->sync($request->product_locations);
        }

        $product->fresh()->load(['variations', 'brand', 'category', 'unit']);

        return $this->success(new ProductResource($product), 'Product updated successfully');
    }

    public function imageUpload($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $product = Product::where('business_id', $business_id)->findOrFail($id);

        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048']);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/img'), $filename);

            if ($product->image && file_exists(public_path('uploads/img/' . $product->image))) {
                @unlink(public_path('uploads/img/' . $product->image));
            }

            $product->image = $filename;
            $product->save();
        }

        return $this->success(new ProductResource($product->fresh()), 'Image uploaded successfully');
    }

    public function stock($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');

        $product = Product::where('business_id', $business_id)->findOrFail($id);

        $stockQuery = VariationLocationDetails::join('variations', 'variation_location_details.variation_id', '=', 'variations.id')
            ->join('products', 'variations.product_id', '=', 'products.id')
            ->where('products.id', $id);

        if ($location_id) {
            $stockQuery->where('variation_location_details.location_id', $location_id);
        }

        $stock = $stockQuery->select(
            'variations.id as variation_id',
            'variations.name as variation_name',
            'variations.sub_sku',
            'variation_location_details.location_id',
            'variation_location_details.qty_available'
        )->get();

        return $this->success($stock);
    }

    protected function getReferenceCount($type)
    {
        $ref = \App\ReferenceCount::where('ref_type', $type)
            ->where('business_id', $this->getBusinessId())
            ->first();

        if (!$ref) {
            return 1;
        }

        return $ref->ref_count + 1;
    }

    protected function generateReferenceNumber($type, $ref_count)
    {
        return $type . '_' . str_pad($ref_count, 4, '0', STR_PAD_LEFT);
    }

    protected function createSingleVariation($product, $data)
    {
        $variation_data = [
            'product_id' => $product->id,
            'name' => 'Default',
            'product_variation_id' => null,
            'sub_sku' => $data['sku'] ?? $product->sku,
            'default_purchase_price' => $data['purchase_price'] ?? 0,
            'dpp_inc_tax' => $data['purchase_price'] ?? 0,
            'profit_percent' => 0,
            'default_sell_price' => $data['selling_price'] ?? 0,
            'sell_price_inc_tax' => $data['selling_price'] ?? 0,
        ];

        return \App\Variation::create($variation_data);
    }
}
