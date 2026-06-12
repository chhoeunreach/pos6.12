<?php

namespace App\Exports;

use App\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize
{
    public function query()
    {
        $business_id = request()->session()->get('user.business_id');

        return Product::where('business_id', $business_id)
            ->with([
                'brand',
                'unit',
                'category',
                'sub_category',
                'product_variations',
                'product_variations.variations',
                'product_tax',
                'rack_details',
                'product_locations',
            ])
            ->select('products.*');
    }

    public function headings(): array
    {
        return ['NAME', 'BRAND', 'UNIT', 'CATEGORY', 'SUB-CATEGORY', 'SKU (Leave blank to auto generate sku)', 'BARCODE TYPE', 'MANAGE STOCK (1=yes 0=No)', 'ALERT QUANTITY', 'EXPIRES IN', 'EXPIRY PERIOD UNIT (months/days)', 'APPLICABLE TAX', 'Selling Price Tax Type (inclusive or exclusive)', 'PRODUCT TYPE (single or variable)', 'VARIATION NAME (Keep blank if product type is single)', 'VARIATION VALUES (| seperated values & blank if product type if single)', 'VARIATION SKUs (| seperated values & blank if product type if single)', 'PURCHASE PRICE (Including tax)', 'PURCHASE PRICE (Excluding tax)', 'PROFIT MARGIN', 'SELLING PRICE', 'OPENING STOCK', 'OPENING STOCK LOCATION', 'EXPIRY DATE', 'ENABLE IMEI OR SERIAL NUMBER(1=yes 0=No)', 'WEIGHT', 'RACK', 'ROW', 'POSITION', 'IMAGE', 'PRODUCT DESCRIPTION', 'PRODUCT KEYWORD', 'CUSTOM FIELD 2', 'CUSTOM FIELD 3', 'CUSTOM FIELD 4', 'NOT FOR SELLING(1=yes 0=No)', 'PRODUCT LOCATIONS', 'PRODUCT KEYWORDS'];
    }

    public function map($product): array
    {
        $product_variation = $product->product_variations->first();
        $variations = optional($product_variation)->variations ?? collect();

        $product_variation_name = $product->type == 'variable' ? optional($product_variation)->name : '';
        $variation_values = $product->type == 'variable' ? $variations->pluck('name')->implode('|') : '';
        $variation_skus = $variations->pluck('sub_sku')->implode('|');
        $purchase_prices = $variations->pluck('dpp_inc_tax')->implode('|');
        $purchase_prices_ex_tax = $variations->pluck('default_purchase_price')->implode('|');
        $profit_percents = $variations->pluck('profit_percent')->implode('|');
        $selling_prices = $product->tax_type == 'inclusive'
            ? $variations->pluck('sell_price_inc_tax')->implode('|')
            : $variations->pluck('default_sell_price')->implode('|');
        $locations = $product->product_locations->pluck('name')->implode(',');
        $product_keywords = $variations->pluck('product_keywords')->implode('|');

        $rack_details = [];
        $row_details = [];
        $position_details = [];
        foreach ($product->product_locations as $location) {
            foreach ($product->rack_details as $rack_detail) {
                if ($rack_detail->location_id == $location->id) {
                    $rack_details[] = $rack_detail->rack;
                    $row_details[] = $rack_detail->row;
                    $position_details[] = $rack_detail->position;
                }
            }
        }

        return [
            $product->name,
            $product->brand->name ?? '',
            $product->unit->short_name ?? '',
            $product->category->name ?? '',
            $product->sub_category->name ?? '',
            $product->sku,
            $product->barcode_type,
            $product->enable_stock,
            $product->alert_quantity,
            $product->expiry_period,
            $product->expiry_period_type,
            $product->product_tax->name ?? '',
            $product->tax_type,
            $product->type,
            $product_variation_name,
            $variation_values,
            $variation_skus,
            $purchase_prices,
            $purchase_prices_ex_tax,
            $profit_percents,
            $selling_prices,
            '',
            '',
            '',
            $product->enable_sr_no,
            $product->weight,
            implode('|', $rack_details),
            implode('|', $row_details),
            implode('|', $position_details),
            $product->image_url,
            $product->product_description,
            $product->product_custom_field1,
            $product->product_custom_field2,
            $product->product_custom_field3,
            $product->product_custom_field4,
            $product->not_for_selling,
            $locations,
            $product_keywords,
        ];
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
