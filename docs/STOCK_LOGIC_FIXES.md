# Stock workflow fixes

The subsequent administrator-wide Authority preview and repair workflow is documented in [AUTHORITY_FULL_REPAIR.md](AUTHORITY_FULL_REPAIR.md).

These changes address the code paths identified in `STOCK_LOGIC_REVIEW.md`. They do not rewrite historical stock records.

## Changes

- Mobile transfers now use `StockTransferStockService` to allocate source receipts, preserve lots and expiry dates, build destination receipts, and move location stock atomically. Pending transfers remain pending until completed through the shared service.
- Mobile stock adjustments now use real schema fields and allocate their loss against receipts. Both normal and abnormal adjustments deduct stock.
- Mobile purchase edits retain allocation checks, reject location changes, and identify existing rows by distinct `purchase_line_id` values. Legacy matching remains available when exactly one existing row matches; ambiguous rows require IDs.
- Receipt edits cannot reduce quantities below consumed stock, change a line's product identity, or remove receipts referenced by movements. Received purchases cannot be unreceived or deleted while referenced. Return allocations are preserved, and purchase reconciliation compares net consumption after customer returns.
- Browser and mobile purchase edits/status changes read the previous purchase state inside a database transaction while holding a parent row lock. Repeated receipt status requests do not add stock again.
- Browser and mobile supplier returns share `PurchaseReturnStockService`. It validates original receipt ownership, nonnegative quantities, unit precision, sales/transfers, adjustments, manufacturing usage, and lot history. Edits apply only the return delta.
- Mobile supplier returns update original receipt counters and a linked return document with actual receipt costs and totals. Return response lines are derived from the original receipt; they are not extra incoming stock.
- Mobile customer returns pass the authorized parent sale, use its original prices and lot allocations, and normalize dates/numbers. Return creation locks the parent sale and validates line ownership.
- Completed transfer date edits use the existing Verified Delivery Date Correction workflow. Purchase dates cannot move after completed transfers that consumed their receipts.
- Transfer import previews include manufacturing consumption. Draft/quotation conversion retains combo component product IDs.
- API purchase quantities are handled as database numbers rather than locale-formatted text. Stock deltas no longer round quantities through the currency precision.

## Stock history transfer totals

Stock history now aggregates receipt, sale, and adjustment lines by transaction for the selected variation before joining them. Eleven one-unit lots on the same transfer appear as one incoming +11 row (and one outgoing -11 row at the source), instead of eleven separate +1 rows. Other variations on the invoice are excluded. Aggregating before joining also prevents multiple original receipt rows from multiplying return quantities. History is scoped to the current business, with transaction ID breaking ties in movement dates.

Opening the stock-history page no longer updates `variation_location_details.qty_available`. Any persisted mismatch must be investigated and repaired through a reviewed repair operation.

Regression tests cover eleven-lot transfer totals, receipt/return joins, sale-return fallback, business scoping, and the absence of database writes when viewing history. The subsequently supplied `ky.sql` export confirms an actual receiving-record deficit for ST2026/1499: SKU 10100793-23 has 10 outgoing units and only 1 receiving unit. History now flags unequal source and receiving quantities, including completely missing receiving lines, without substituting the outgoing quantity for the stored receipt quantity. See [the export findings](diagnostics/ST2026-1499-findings.md) for missing receipts and the original-record evidence required for restoration. The running MySQL database remains inaccessible; no live balances were changed.

## API behavior

For purchase edits, send `products[].purchase_line_id` for existing lines, especially duplicate SKU/lot rows. Transfer and loss adjustment requests may send `products[].lot_no_line_id`. API return quantities are base-unit quantities; supplier return POSTs add to the cumulative return. Return prices come from the original purchase/sale.

Stock validation failures return HTTP 422 with an explanation. Moving a purchase to another location requires a stock transfer. Consumed receipts and completed transfer dates require their existing reconciliation/correction workflows.

## Verification

- `StockWorkflowRegressionTest`: 22 tests, 79 assertions passed against a dedicated in-memory SQLite connection.
- Existing lot/cost selection: 237 tests exercised. 231 passed initially; six template-rendering tests encountered the existing view-cache directory permissions and all six passed when rerun with a writable temporary view cache (46 assertions). Together with the 22 new tests, all 259 distinct stock tests passed across these runs.
- Targeted existing purchase/transfer edit checks also passed: 5 tests, 34 assertions.
- PHP syntax and Git whitespace checks passed for the changed files.

Tests used `APP_ENV=testing LOG_CHANNEL=stderr DB_CONNECTION=sqlite DB_DATABASE=:memory:` and `--do-not-cache-result`. Template tests additionally used `VIEW_COMPILED_PATH=/tmp/pos-stock-fix-views` (created before the run). No application cache or permission configuration was changed. SQLite checks establish workflow and rollback behavior; simultaneous MySQL request locking still needs verification in a staging environment. The PHP 8.5 test runtime emits existing PDO constant deprecation notices.

## Existing mismatches

After these fixes, use the existing read-only `php artisan stock:audit-lots BUSINESS_ID` command against a database copy to identify affected receipts, allocations and transfers. A historical repair must follow the actual documents and serials. No stock reconciliation command, migration, or production data repair was executed as part of this change.
