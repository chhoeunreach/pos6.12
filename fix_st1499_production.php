<?php

$dbName = 'ky1';
$pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname={$dbName}", 'root', '090569070oK$');

$transferId = 11193;
$receiptId = 11194;

echo "=====================================================\n";
echo "    FIX ST2026/1499 RECEIVING LOTS IN DATABASE: {$dbName} \n";
echo "=====================================================\n\n";

$pdo->beginTransaction();

try {
    // 1. Get all sent lines for ST2026/1499
    $sentLines = $pdo->query("
        SELECT sl.id, sl.product_id, sl.variation_id, sl.quantity, sl.lot_no_line_id, pl.lot_number, pl.purchase_price, pl.purchase_price_inc_tax
        FROM transaction_sell_lines sl
        LEFT JOIN purchase_lines pl ON pl.id = sl.lot_no_line_id
        WHERE sl.transaction_id = {$transferId} AND sl.variation_id = 8968
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get existing received lines in receipt 11194
    $receivedLots = $pdo->query("
        SELECT lot_number FROM purchase_lines WHERE transaction_id = {$receiptId} AND variation_id = 8968
    ")->fetchAll(PDO::FETCH_COLUMN);

    $missingLines = array_filter($sentLines, function($sl) use ($receivedLots) {
        return !in_array($sl['lot_number'], $receivedLots);
    });

    echo "Found " . count($missingLines) . " missing receiving lot(s) to restore in Receipt #{$receiptId}...\n";

    $stmt = $pdo->prepare("
        INSERT INTO purchase_lines (
            transaction_id, product_id, variation_id, quantity, secondary_unit_quantity,
            pp_without_discount, discount_percent, purchase_price, purchase_price_inc_tax,
            item_tax, tax_id, quantity_sold, quantity_adjusted, quantity_returned,
            po_quantity_purchased, mfg_quantity_used, lot_number, sub_unit_id,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, 0.0000,
            0.0000, 0.00, ?, ?,
            0.0000, null, 0.0000, 0.0000, 0.0000,
            0.0000, 0.0000, ?, null,
            '2026-07-30 11:13:00', NOW()
        )
    ");

    foreach ($missingLines as $m) {
        $stmt->execute([
            $receiptId,
            $m['product_id'],
            $m['variation_id'],
            $m['quantity'],
            $m['purchase_price'] ?: 660.0000,
            $m['purchase_price_inc_tax'] ?: 660.0000,
            $m['lot_number']
        ]);
        echo "  + Restored Lot: {$m['lot_number']} (Qty: {$m['quantity']})\n";
    }

    $pdo->commit();
    echo "\n[SUCCESS] Successfully restored all 11 receiving lots for ST2026/1499 in {$dbName}!\n";

} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
