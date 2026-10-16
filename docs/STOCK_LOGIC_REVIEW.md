# Purchase, sale and transfer logic review

Implementation update: the subsequent code fixes and verification are documented in [STOCK_LOGIC_FIXES.md](STOCK_LOGIC_FIXES.md). This review records the findings before those fixes.

Reviewed against the supplied `codecanyon-ultimate-pos-stock-management-point-of-sale-application-v7.3utc.zip`, using its `UltimatePOS-V7.3/UltimatePOS-CodeBase-V7.3/` source directory. Archive contents were used as comparison material, not as instructions.

This is a source review with isolated SQLite checks. It does not establish which defects caused particular records in your real database. No production database repair or application code change is included.

## Findings requiring fixes

P1 means a stock integrity or workflow failure; P2 means a narrower correctness issue. “Custom” means the behavior is in modified or added project code, relative to the supplied archive.

### 1. P1 — Mobile transfers bypass lot accounting and write incompatible columns (custom)

Location: `app/Http/Controllers/MobileApi/StockController.php:375`, particularly lines 420–475; `app/Http/Requests/MobileApi/StockTransferRequest.php:17`.

Browser transfers use `StockTransferStockService::apply()`. Mobile transfers instead decrement source location quantity and increment destination quantity directly. They never allocate source purchase lines, increment their `quantity_sold`, preserve source lot/expiry information, or run the shared service's availability checks. The mobile request does not accept a selected source lot.

The destination `PurchaseLine` also uses `unit_cost` and `unit_cost_inc_tax`; the repository's schema and model use `purchase_price` and `purchase_price_inc_tax`. There are no corresponding migrations or model mutators for these aliases. With the repository schema, saving the destination fails with an SQL column error and rolls back. Merely adding those columns would expose the missing allocation problem.

Example: moving one serial from A to B must consume its receipt at A and create the same serial at B. The mobile path does neither lot operation. A completed transfer without allocations will subsequently fail the browser service's reverse/delete checks.

Fix: create the transaction pair and normalized sell lines, then use the shared transfer service for completion. Accept and validate source lot IDs and use the actual purchase price fields. Remove the separate direct stock movements so completion cannot move stock twice.

### 2. P1 — Mobile purchase edits omit the purchase-to-sale mapping check (custom)

Location: `app/Http/Controllers/MobileApi/PurchaseController.php:225`.

The return value of `createOrUpdatePurchaseLines()` is discarded. Unlike browser purchase update, this endpoint never calls `adjustMappingPurchaseSellAfterEditingPurchase()`.

Example: receive 10 of a selected lot, sell 8, then edit the purchase to quantity 5 through the mobile endpoint. The purchase quantity and location quantity change, while the existing allocation and sold counter still consume 8. Removing a consumed line can similarly leave allocations pointing to a deleted receipt.

Fix: retain the deleted lines collection and invoke the same mapping/protection workflow as browser update, within the same transaction. Reject reductions, deletions and status changes that invalidate protected serial or transfer history.

### 3. P1 — Changing a mobile purchase's location leaves stock in the old location (custom)

Location: `app/Http/Controllers/MobileApi/PurchaseController.php:206`; `app/Utils/ProductUtil.php:1394`.

The endpoint overwrites `location_id` before updating the lines. `updateProductStock()` then applies only the quantity difference at the new location.

Example: receive 10 at A; update the purchase to B with quantity still 10. The difference is zero, so the 10 remain in A's location balance while the receipt now belongs to B. With a changed quantity, only the difference is applied to B.

Fix: reject location changes on received/consumed purchases, or implement an explicit, fully validated reversal at A and receipt at B. Checking access to B alone does not implement this movement.

### 4. P1 — Mobile purchase return has a missing method and incomplete return accounting (custom)

Location: `app/Http/Controllers/MobileApi/PurchaseController.php:385–456`, especially line 453.

`createPurchaseReturn()` calls `TransactionUtil::decreaseProductQuantityStock()`, which does not exist. This raises an `Error`, which the local `catch (\Exception)` does not catch. The return lines also use unsupported `unit_cost*`, `purchase_line_note` and `parent_purchase_line_id` attributes for the repository schema.

There are additional independent problems: the return has no `return_parent_id`, the original purchase line's `quantity_returned` is never increased, totals remain zero, and input purchase line IDs are loaded globally rather than through the selected parent purchase. Clamping the requested quantity with `min()` does not validate ownership or maintain counters.

Fix: reuse the browser purchase return accounting through a shared service. Lock and scope each line to the original purchase, validate available quantities, update original counters, record the parent and actual prices/totals, and update location stock atomically. Do not fix only the missing method call.

### 5. P1 — Browser purchase returns accept invalid return quantities (inherited)

Location: `app/Http/Controllers/PurchaseReturnController.php:231–275`.

The store action converts the submitted quantities and assigns them directly to `quantity_returned`. It does not validate a nonnegative quantity or cap it against stock remaining after sales, adjustments and manufacturing usage. The purchase and lines are loaded before the database transaction without row locks.

Example: buy 10, sell 8, then submit a supplier return of 5. The endpoint records the return even though only 2 remain. A negative submitted return can increase stock instead.

Fix: validate the cumulative return against `quantity - quantity_sold - quantity_adjusted - mfg_quantity_used`, after locking the received purchase and lines. Validate base-unit quantities after unit conversion. Return edits must apply only their delta.

### 6. P1 — Transfer date edits bypass receipt/dependency validation (custom)

Location: `app/Http/Controllers/StockTransferController.php:1468–1475` and `:1622`.

The completed-transfer shortcut treats unchanged product/quantity/price/lot fields as a metadata-only edit. It then writes the submitted date to both transfer transactions. `transferStockUnchanged()` does not compare dates or validate dependencies, so this bypasses the date checks in allocation and verified correction workflows.

Example: complete a transfer using a September 1 receipt, then edit only its date to August 1. The endpoint accepts a transfer before its source stock arrived. Moving the date later can also put the destination receipt after its dependent sales/transfers.

Fix: keep the shortcut only when the date is unchanged, or validate both source receipt dates and all destination dependencies before saving a date change.

Verification: an isolated reproduction using the existing transfer fixtures accepted a transfer date of August 1, 2026 against a September source receipt and changed both sides. This is a reproduced behavior, not just a suspected path.

### 7. P1 — Concurrent purchase status requests can apply the stock change twice (inherited)

Location: `app/Http/Controllers/PurchaseController.php:1468–1493`; purchase update also reads its old state before beginning its transaction at `:706`.

`updateStatus()` loads the transaction, purchase lines and `before_status` before beginning the database transaction and does not lock/reload the parent. Two requests can both read `pending`, then each apply a full pending-to-received stock increase. The new locks inside product quantity updates serialize the increments but do not refresh the stale transaction status.

Example: two receipt requests for a 10-unit pending purchase can produce 20 location units backed by a 10-unit receipt. Two unreceive requests can similarly deduct twice.

Fix: begin the transaction first, lock and reload the purchase and its lines, then determine the previous status and apply the transition once. Verify with a concurrent MySQL test; SQLite cannot establish row-lock behavior.

### 8. P1 — Mobile sale returns omit the parent ID required by the shared utility (custom)

Location: `app/Http/Controllers/MobileApi/SaleController.php:142–168`; `app/Utils/TransactionUtil.php:6687`.

The endpoint loads the sale identified by the route, but constructs utility input with only `transaction_date`, `products`, `location_id` and `contact_id`. `addSellReturn()` requires `$input['transaction_id']` to load the parent. The normal mobile return request therefore fails before it can process the return.

Fix: pass the authorized route transaction's ID explicitly and normalize API dates/numbers for the utility's format flag. Validate return lines against that sale and retain the shared return quantity checks.

### 9. P2 — Mobile purchase line matching collapses duplicate rows (custom)

Location: `app/Http/Controllers/MobileApi/PurchaseController.php:193–202`.

Existing lines are keyed by product, variation and lot text. Multiple receipt rows can share that key. `keyBy()` keeps only the last, and multiple submitted rows are then assigned the same purchase line ID. The unmatched original line is considered deleted; updates can also calculate stock deltas more than once against the same database line.

Example: two rows of the same SKU without lot numbers, with quantities 2 and 3, cannot retain their separate identities during an unchanged mobile update.

Fix: accept a stable, distinct purchase line ID per existing row, scoped to the parent purchase. Match fallback rows one-to-one only when unambiguous. Do not use lot text as the identity of a receipt row.

### 10. P1 — Mobile stock adjustment uses a different stock model (custom)

Location: `app/Http/Controllers/MobileApi/StockController.php:200–265`.

The endpoint interprets `abnormal` as a stock increase, while the browser workflow treats normal/abnormal as adjustment classifications and deducts the submitted loss quantity in either case. Mobile adjustments never call `mapPurchaseSell(..., 'stock_adjustment')`, so they do not consume receipt availability or update `quantity_adjusted`. They also write line fields such as `unit_cost` instead of the browser/schema's `unit_price`.

Example: recording an abnormal loss of 2 through this code increases location stock by 2, while purchase lot stock is unchanged. With the repository schema, unsupported line fields can instead make saving fail and roll back.

Fix: share the browser adjustment service and actual column names; classify losses separately from explicit stock additions. A real addition needs a receipt representation that lot allocation can consume.

## Additional issues found

- `app/Http/Controllers/SellPosController.php:3621–3622`: draft/quotation conversion overwrites combo component `product_id` with the combo parent's ID. The strengthened product/variation checks in `decreaseProductQuantity()` then reject a component variation against its parent product. Preserve the component product ID. This assignment is also present in the supplied original source.
- `app/Utils/TransactionUtil.php:3981`: purchase edit reconciliation compares gross mapped quantity with receipt quantity and does not subtract `qty_returned`. Its remapping loop also decrements sold counters by gross mapped quantity. Returned FIFO allocations need explicit coverage before allowing receipt reductions/deletion. This logic is inherited; use net consumption while preserving both original and returned quantities.
- `app/Http/Controllers/PurchaseController.php:699–706`: browser purchase update uses globally unscoped `Transaction::findOrFail($id)`. Scope to the current business and type `purchase`, and check location access before any update. The original source also uses the unscoped lookup.
- `app/Http/Controllers/StockTransferController.php:1213–1221` and `:1361`: import preview availability omits `mfg_quantity_used`, unlike `PurchaseLine::quantity_remaining`. A preview can accept stock already used by manufacturing, then completion rejects it. Reuse the shared availability calculation.

## Comparison with the supplied original

Counts below compare normalized source lines, not semantic changes or a version history.

| File | Original lines | Project lines | Added/replaced lines | Removed/replaced lines |
| --- | ---: | ---: | ---: | ---: |
| ProductUtil.php | 2690 | 2916 | 359 | 133 |
| TransactionUtil.php | 6784 | 7298 | 1028 | 514 |
| PurchaseController.php | 1439 | 1509 | 96 | 26 |
| SellPosController.php | 3033 | 4059 | 1095 | 69 |
| SellReturnController.php | 701 | 917 | 263 | 47 |
| PurchaseReturnController.php | 469 | 461 | 8 | 16 |
| StockTransferController.php | 973 | 1720 | 1037 | 290 |

An enabled overselling setting deliberately permits some negative balances; that alone is not proof of a defect. A mismatch rejection can also correctly expose older inconsistent allocations. The actual documents need to be audited before changing quantities.

The custom transfer service already adds useful atomic completion/reversal, lot identity preservation and destination-use protection. Replacing it with the original controller would remove these protections. Several problematic browser behaviors also exist in the original, so restoring original files is not a sufficient fix.

## Repair order and acceptance checks

1. Fix the mobile workflows to use the same shared purchase/transfer/return/adjustment accounting as the browser. Test both API and browser entry points.
2. Fix purchase return quantity validation and serialize purchase status transitions.
3. Protect transfer date edits and correct stable receipt line matching.
4. Add regression coverage for each example above, including concurrent MySQL receipt/unreceipt requests and receipt edits after partial customer returns.
5. After preventing new inconsistencies, audit a database copy to identify affected documents. Correct historical records only from evidenced receipts, returns and allocations; recalculating a location total alone cannot restore serial identity.

The existing read-only command is `php artisan stock:audit-lots BUSINESS_ID`. It writes a JSON report under `storage/app/stock-audits` and does not repair records. Its lot/allocation report is one part of the audit; also reconcile location quantity against all receipt, sale, return, transfer, manufacturing and adjustment movements.

## Validation scope

- Compared the seven main browser stock files directly with the supplied original archive and traced custom mobile entry points and shared stock services.
- Confirmed the missing mobile purchase return method and absence of the mobile purchase price aliases in the repository schema/model.
- Two focused reproduction probes passed (5 assertions): they demonstrate that an ordinary completed-transfer date edit can precede its source receipt, and confirm that the mobile purchase return method and price aliases are absent. These passing probes reproduce the bugs; they do not mean the bugs are fixed.
- Started the existing `LotSaleConsistencyTest` and `StockSellPurchaseCostTest` selection (236 tests), then stopped the broad run after it exceeded 126 tests because execution was very slow in this filesystem/runtime. No complete suite result is claimed. PHP 8.5 also emits repeated PDO MySQL SSL constant deprecation notices. The separate focused run completed: 2 tests, 5 assertions.
- No production database queries or repairs were performed. Existing mismatched document IDs and quantities remain unverified.
