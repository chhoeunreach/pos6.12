# Old V7.3 versus current code: transfer serial mismatch

Compared on 18 October 2026 against the supplied `UltimatePOS-CodeBase-V7.3.zip`.

Archive SHA-256: `a9f98d08d036e64a26ecbf55af65d1af435233a9e2dbc3807fe828aded682da8`.

## What the warning actually checks

The current `app/Services/LotStockAuditService.php`, lines 139–182, groups outgoing movements by product, variation and source serial. It compares each group's quantity with the linked received transfer's purchase lines for that same product, variation and serial. A difference greater than 0.0001 produces:

> Selected source serial and received serial quantities differ.

For explicitly selected receipts, the source serial comes from `transaction_sell_lines.lot_no_line_id`. For FIFO movements without a selected receipt, it comes from the actual source allocations. A transfer can have matching overall quantities and still fail this per-serial comparison.

This audit service is absent from the supplied archive. Its absence does not establish that old saved transfers were correct.

## Confirmed defect 1: repeated SKU rows overwrite one receiving row

Archive: `app/Http/Controllers/StockTransferController.php`, `update()`, lines 784–798:

```php
foreach ($purchase_transfer->purchase_lines as $pl) {
    if ($pl->variation_id == $purchase_line_arr['variation_id']) {
        $pl->update($purchase_line_arr);
        $edited_purchase_lines[] = $pl->id;
        $purchase_line = $pl;
        break;
    }
}
```

The search uses only `variation_id`. Every incoming row for the same SKU finds the first existing receiving row again. It neither matches the serial nor reserves a different receiving row for each outgoing row.

Lines 821–826 then delete receiving rows whose IDs were not selected:

```php
PurchaseLine::where('transaction_id', $purchase_transfer->id)
    ->whereNotIn('id', $edited_purchase_lines)
    ->delete();
```

Example reproduced: two outgoing rows of the same SKU, SERIAL-A × 1 and SERIAL-B × 1. The first receiving row is updated twice, ending as SERIAL-B × 1; the second receiving row is deleted. Both outgoing rows remain. Result: **Sent 2, Received 1**, with no matching receiving quantity for SERIAL-A. The destination location balance can still say 2 because the old update increments it from the two submitted product rows independently of the surviving receiving records.

The current controller no longer uses this variation-only matching loop. Its receiving batches are built from the source allocations by `StockTransferStockService::apply()`. The corresponding current-code reproduction retained both serials and both units.

## Confirmed defect 2: changing the serial does not update the saved source selection

The old controller reads the submitted `lot_no_line_id` and copies that receipt's serial into the receiving row (`StockTransferController.php`, lines 760–769). However, the archived `TransactionUtil::editSellLine()` updates the outgoing row using an explicit `fill()` field list (`app/Utils/TransactionUtil.php`, lines 591–607) that does not include `lot_no_line_id`.

Reproduced result after changing SERIAL-A to SERIAL-B: outgoing source selection remains SERIAL-A, while the receiving row becomes SERIAL-B. Both quantities equal 1, but SERIAL-A's matching received quantity is 0, so the current audit reports the warning.

The current controller explicitly updates the existing outgoing line's `lot_no_line_id` before saving the edited lines (`app/Http/Controllers/StockTransferController.php`, line 1560). The corresponding reproduction recorded SERIAL-B on both sides and produced no transfer audit issue.

## Confirmed defect 3: FIFO allocations are not copied back into receiving serials

In the old `store()` flow, receiving serials are copied only when the request contains an explicit `lot_no_line_id` (lines 269–279). The receiving rows are saved first (lines 313–314), and `mapPurchaseSell()` chooses FIFO source receipts later (line 355). The receiving rows are not subsequently rebuilt from those allocations.

Reproduced result without an explicitly selected lot: FIFO deducted SERIAL-A × 2, while the destination received a blank-serial row of quantity 2. Overall quantities match, but SERIAL-A's matching received quantity is 0.

The current shared stock service maps source allocations first, then creates receiving rows from those actual source receipts. Its corresponding reproduction received SERIAL-A × 2 with no transfer audit issue.

## Comparison results

| Scenario | Archived controller | Current controller |
| --- | --- | --- |
| Edit two serials of one SKU | Sent 2; received 1; SERIAL-A missing | Sent 2; received 2; both serials present |
| Change a line from SERIAL-A to SERIAL-B | Source A; received B; quantities 1 each | Source B; received B; quantities 1 each |
| Create FIFO transfer without selecting a receipt | Source A × 2; receiving serial blank × 2 | Source A × 2; receiving serial A × 2 |

All three archived-controller scenarios produced the exact warning through the current audit. All three current-controller counterparts produced zero transfer audit issues. **Six comparison tests passed, with 38 assertions.** Raw fixture results are in `ultimatepos-v7.3-transfer-comparison.json` beside this report.

Method: only selected PHP/JS files were extracted to `/tmp/pos-old-code-comparison`; the installed application was not replaced. The archived controller's class name was changed solely to load it beside the current controller. Both ran against isolated SQLite fixtures and the current model/utility test harness. The overselling reconciliation hook was disabled in both because the fixtures contain no oversold movements; the old hook is a no-op for these fixtures. This is a focused controller comparison, not a test of the entire archived installation. The archived utility's source-selection omission was also inspected directly.

## Implications for the reported invoices

The repeated-SKU defect is a confirmed way to create the reported Sent 2 / Received 1 pattern. The other two defects explain why the serial warning can also appear when overall quantities match.

Which operation affected ST2026/3498-1, ST2026/0159-1 or ST2026/4375-1 is not established by a code archive. Their current rows, allocations and edit history are still needed. Installing corrected code does not restore previously deleted receipt IDs or recover the original serial attached to those IDs.

The comparison also identified an administrator force override of the used-receipt guard. The subsequent correction removes that bypass from ordinary transfer edits, completion, reversal and deletion. Legacy service arguments cannot bypass the receiving-history check either. Stock-changing edits are rejected when later movements reference receiving rows; metadata-only edits preserve those rows. Completion additionally checks received quantities for each product, variation and serial against the actual source allocations. Permanent tests cover repeated-SKU edits, changed source selections, administrator edits of referenced receipts, forced reversal of consumed receipts and rollback on serial disagreement with equal overall quantity.

No live records or application PHP/JS files were changed during the initial comparison. Application code was subsequently corrected at the user's request; the original ZIP remains unchanged.
