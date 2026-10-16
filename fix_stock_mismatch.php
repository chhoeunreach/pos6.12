<?php

/**
 * Script to fix Stock Quantity Mismatches and Receiving Lot Mismatches.
 *
 * Usage:
 *   php fix_stock_mismatch.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Utils\ProductUtil;
use Modules\SmartStockInventory\Models\SmartStockFixLog;

echo "=====================================================\n";
echo "       STOCK MISMATCH REPAIR SCRIPT                 \n";
echo "=====================================================\n\n";

$businessId = 1;
$productUtil = app(ProductUtil::class);

DB::beginTransaction();

try {
    // -------------------------------------------------------------
    // 1. FIX ISSUE AT LOCATION 1 (កម្ពុជាក្រោម - BL0001)
    // Product: SC Iphone 15 Pro Max LA 256GB - Natural Titanium (Variation: 8968)
    // Recorded stock: 25.00 -> Correct calculated stock: 36.00
    // -------------------------------------------------------------
    echo "[1/2] Checking Location 1 (កម្ពុជាក្រោម) for Variation 8968...\n";
    
    $vld1 = DB::table('variation_location_details')
        ->where('variation_id', 8968)
        ->where('location_id', 1)
        ->first();

    $calc1 = $productUtil->getVariationStockMisMatch($businessId, 8968, 1)->first();

    if ($vld1 && $calc1) {
        $currentQty = (float) $vld1->qty_available;
        $correctQty = (float) $calc1->total_stock_calculated;

        echo "      Current Recorded Stock : {$currentQty}\n";
        echo "      Correct Calculated Stock: {$correctQty}\n";

        if (abs($currentQty - $correctQty) > 0.0001) {
            echo "      -> Fixing mismatch: Updating {$currentQty} to {$correctQty}...\n";
            
            DB::table('variation_location_details')
                ->where('variation_id', 8968)
                ->where('location_id', 1)
                ->update(['qty_available' => $correctQty]);

            // Create Audit Log in SmartStock
            SmartStockFixLog::create([
                'business_id' => $businessId,
                'location_id' => 1,
                'product_id' => 2452,
                'variation_id' => 8968,
                'fix_type' => 'mismatch_stock',
                'problem_type' => 'mismatch_stock',
                'old_qty' => $currentQty,
                'new_qty' => $correctQty,
                'reason' => 'System Script: Reconcile stock quantity with Stock History',
                'risk_level' => 'low',
                'reference_type' => 'variation_location_details',
                'reference_id' => 8968,
                'before_payload' => json_encode(['qty_available' => $currentQty]),
                'after_payload' => json_encode(['qty_available' => $correctQty]),
                'is_rollback' => 0,
                'rollbackable' => 1,
                'created_by' => 1,
            ]);

            echo "      [SUCCESS] Location 1 stock updated to {$correctQty} Pc(s)!\n";
        } else {
            echo "      [OK] No mismatch found for Location 1.\n";
        }
    }
    echo "\n";

    // -------------------------------------------------------------
    // 2. FIX ISSUE AT LOCATION 12 (ស្ដុកធំ) FOR TRANSFER ST2026/4937-1
    // Sent: 6.00, Received: 5.00 (Missing 1.00 pc receiving lot)
    // -------------------------------------------------------------
    echo "[2/2] Checking Transfer ST2026/4937-1 (Transfer ID: 42940 -> 42941)...\n";

    $transferId = 42940;
    $receiptId = 42941;

    $sentQty = DB::table('transaction_sell_lines')
        ->where('transaction_id', $transferId)
        ->where('variation_id', 8968)
        ->sum('quantity');

    $receivedQty = DB::table('purchase_lines')
        ->where('transaction_id', $receiptId)
        ->where('variation_id', 8968)
        ->sum('quantity');

    echo "      Sent Quantity    : {$sentQty}\n";
    echo "      Received Quantity: {$receivedQty}\n";

    if ((float) $sentQty > (float) $receivedQty) {
        $diff = (float) $sentQty - (float) $receivedQty;
        echo "      -> Fixing mismatch: Restoring {$diff} missing receiving lot(s)...\n";

        DB::table('purchase_lines')->insert([
            'transaction_id' => $receiptId,
            'product_id' => 2452,
            'variation_id' => 8968,
            'quantity' => $diff,
            'secondary_unit_quantity' => 0.0000,
            'pp_without_discount' => 0.0000,
            'discount_percent' => 0.00,
            'purchase_price' => 660.0000,
            'purchase_price_inc_tax' => 660.0000,
            'item_tax' => 0.0000,
            'tax_id' => null,
            'quantity_sold' => 0.0000,
            'quantity_adjusted' => 0.0000,
            'quantity_returned' => 0.0000,
            'po_quantity_purchased' => 0.0000,
            'mfg_quantity_used' => 0.0000,
            'lot_number' => null,
            'sub_unit_id' => null,
            'created_at' => '2026-10-16 18:28:28',
            'updated_at' => now(),
        ]);

        // Reconcile stock at Location 12
        $calc12 = $productUtil->getVariationStockMisMatch($businessId, 8968, 12)->first();
        if ($calc12) {
            DB::table('variation_location_details')
                ->where('variation_id', 8968)
                ->where('location_id', 12)
                ->update(['qty_available' => $calc12->total_stock_calculated]);
        }

        echo "      [SUCCESS] Restored missing receiving lot and synced Location 12 stock!\n";
    } else {
        echo "      [OK] Transfer ST2026/4937-1 is already balanced.\n";
    }

    DB::commit();

    echo "\n=====================================================\n";
    echo "       ALL REPAIRS COMPLETED SUCCESSFULLY!          \n";
    echo "=====================================================\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[ERROR] An error occurred: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
