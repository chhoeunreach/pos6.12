<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Transaction;

class PurchaseLineMatcher
{
    public function match(Transaction $purchase, array $products): array
    {
        $lines = $purchase->purchase_lines()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $used = [];
        // Reserve explicit IDs before attempting backwards-compatible matching.
        foreach ($products as $product) {
            if (empty($product['purchase_line_id'])) {
                continue;
            }
            $id = (int) $product['purchase_line_id'];
            $line = $lines->get($id);
            if (! $line || isset($used[$id]) || (int) $line->product_id !== (int) $product['product_id']
                || (int) $line->variation_id !== (int) $product['variation_id']) {
                throw new PurchaseSellMismatch('Each purchase line ID must be distinct and match this purchase, product and variation.');
            }
            $used[$id] = true;
        }
        foreach ($products as &$product) {
            if (! empty($product['purchase_line_id'])) {
                continue;
            }
            $matches = $lines->filter(fn ($line) => ! isset($used[$line->id])
                && (int) $line->product_id === (int) $product['product_id']
                && (int) $line->variation_id === (int) $product['variation_id']
                && (string) $line->lot_number === (string) ($product['lot_number'] ?? ''));
            if ($matches->count() > 1) {
                throw new PurchaseSellMismatch('Multiple receipt rows match this product. Send a distinct purchase_line_id for each existing row.');
            }
            if ($line = $matches->first()) {
                $product['purchase_line_id'] = $line->id;
                $used[$line->id] = true;
            }
        }
        unset($product);

        return $products;
    }
}
