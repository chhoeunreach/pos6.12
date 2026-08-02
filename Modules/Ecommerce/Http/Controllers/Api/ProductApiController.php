<?php

namespace Modules\Ecommerce\Http\Controllers\Api;

use App\Product;
use App\Variation;
use Illuminate\Http\Request;

class ProductApiController extends \App\Http\Controllers\ProductController
{
    public function getProductsApi($id = null)
    {
        try {
            $api_settings = $this->moduleUtil->getApiSettings(request()->header('API-TOKEN'));
            if (empty($api_settings)) {
                return response()->json(['success' => false, 'message' => 'Invalid API token.'], 401);
            }

            $filters = [];
            parse_str((string) request()->header('FILTERS'), $filters);

            $limit = request()->integer('limit') ?: 10;
            $location_id = $api_settings->location_id;

            $query = Product::where('business_id', $api_settings->business_id)
                ->active()
                ->with([
                    'brand',
                    'unit',
                    'category',
                    'sub_category',
                    'product_variations.variations.media',
                    'product_variations.variations.variation_location_details' => function ($q) use ($location_id) {
                        $q->where('location_id', $location_id);
                    },
                ]);

            if (! empty($filters['categories'])) {
                $query->whereIn('category_id', (array) $filters['categories']);
            }
            if (! empty($filters['brands'])) {
                $query->whereIn('brand_id', (array) $filters['brands']);
            }
            if (! empty($filters['category'])) {
                $query->where('category_id', $filters['category']);
            }
            if (! empty($filters['sub_category'])) {
                $query->where('sub_category_id', $filters['sub_category']);
            }

            if (request()->header('ORDER-BY') == 'name') {
                $query->orderBy('name', 'asc');
            } elseif (request()->header('ORDER-BY') == 'date') {
                $query->orderBy('created_at', 'desc');
            }

            if (! empty($id)) {
                $product = $query->find($id);

                return $this->respond(empty($product) ? null : $this->formatProduct($product));
            }

            $products = $query->paginate($limit);
            $products->getCollection()->transform(function ($product) {
                return $this->formatProduct($product);
            });

            return $this->respond($products);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }
    }

    public function getVariationsApi()
    {
        try {
            $api_settings = $this->moduleUtil->getApiSettings(request()->header('API-TOKEN'));
            if (empty($api_settings)) {
                return response()->json(['success' => false, 'message' => 'Invalid API token.'], 401);
            }

            $variation_ids = $this->variationIdsFromRequest(request());
            $location_id = $api_settings->location_id;
            $business_id = $api_settings->business_id;

            $query = Variation::with([
                'product_variation',
                'product' => function ($q) use ($business_id) {
                    $q->where('business_id', $business_id)->active();
                },
                'product.unit',
                'media',
                'variation_location_details' => function ($q) use ($location_id) {
                    $q->where('location_id', $location_id);
                },
            ])->whereHas('product', function ($q) use ($business_id) {
                $q->where('business_id', $business_id)->active();
            });

            if (! empty($variation_ids)) {
                $query->whereIn('id', $variation_ids);
            }

            $variations = $query->get()->map(function ($variation) {
                return $this->formatVariation($variation);
            });

            return $this->respond($variations);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }
    }

    protected function variationIdsFromRequest(Request $request)
    {
        $ids = $request->input('variation_ids', $request->input('variations', $request->header('VARIATIONS')));

        if (empty($ids)) {
            return [];
        }

        if (is_string($ids)) {
            if (str_contains($ids, '=') || str_contains($ids, '&')) {
                $parsed = [];
                parse_str($ids, $parsed);
                $ids = ! empty($parsed) ? $parsed : [];
            } else {
                $ids = explode(',', $ids);
            }
        }

        return collect((array) $ids)->flatten()->filter()->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();
    }

    protected function formatProduct(Product $product)
    {
        $variations = collect($product->product_variations)->flatMap(function ($product_variation) use ($product) {
            return $product_variation->variations->map(function ($variation) use ($product_variation, $product) {
                $variation->setRelation('product_variation', $product_variation);
                $variation->setRelation('product', $product);

                return $this->formatVariation($variation);
            });
        })->values();

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'name' => $product->name,
            'type' => $product->type,
            'brand' => optional($product->brand)->name,
            'category' => optional($product->category)->name,
            'sub_category' => optional($product->sub_category)->name,
            'unit' => optional($product->unit)->short_name,
            'enable_stock' => (int) $product->enable_stock,
            'qty_available' => (float) $variations->sum('qty_available'),
            'can_print' => true,
            'print_name' => $product->name,
            'image_url' => $product->image_url,
            'variations' => $variations,
        ];
    }

    protected function formatVariation(Variation $variation)
    {
        $product = $variation->product;
        $variation_name = $variation->name == 'DUMMY' ? '' : $variation->name;
        $product_variation_name = optional($variation->product_variation)->name == 'DUMMY' ? '' : optional($variation->product_variation)->name;
        $qty_available = optional($variation->variation_location_details->first())->qty_available;
        $display_name = empty($variation_name) ? optional($product)->name : trim(optional($product)->name.' - '.$product_variation_name.' - '.$variation_name, ' -');

        return [
            'id' => $variation->id,
            'variation_id' => $variation->id,
            'product_id' => $variation->product_id,
            'sku' => $variation->sub_sku ?: optional($product)->sku,
            'sub_sku' => $variation->sub_sku,
            'product_sku' => optional($product)->sku,
            'product_name' => optional($product)->name,
            'name' => $display_name,
            'variation_name' => $variation_name,
            'product_variation_name' => $product_variation_name,
            'color' => $variation_name,
            'qty_available' => (float) ($qty_available ?? 0),
            'quantity' => (float) ($qty_available ?? 0),
            'unit' => optional(optional($product)->unit)->short_name,
            'price' => (float) $variation->sell_price_inc_tax,
            'unit_price_inc_tax' => (float) $variation->sell_price_inc_tax,
            'can_print' => true,
            'print_name' => $display_name,
            'image_url' => optional($variation->media->first())->display_url ?: optional($product)->image_url,
        ];
    }
}
