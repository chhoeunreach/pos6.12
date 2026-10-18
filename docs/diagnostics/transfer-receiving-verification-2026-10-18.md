# Transfer receiving repair verification — 18 October 2026

## Verification scope

This report covers the code and isolated test databases. It does not certify the current MySQL records: the earlier read-only connection attempt was rejected with MySQL error 1045. No live transfer or stock balance has been changed in this session.

Reported records:

| Receiving reference | SKU supplied |
| --- | --- |
| ST2026/3498-1 | 10100595-21 |
| ST2026/0159-1 | 10100595-24 |
| ST2026/4375-1 | Not supplied |

Tests using these references are synthetic reproductions, not copies of the reported invoices.

## Repairs and safeguards

- Completion checks source allocation totals and persisted receiving quantities per product/variation and serial. A disagreement rolls back documents, receipt counters and location balances, including when overall quantities match but serials differ.
- Ordinary transfer edits, completion, reversal and deletion protect receiving rows referenced by later movements for administrators as well as other users. Legacy force arguments do not bypass that check. Metadata-only edits preserve existing receiving rows.
- Receiving reconstruction uses the actual destination document ID, including when every receiving row is missing.
- Same-serial quantities are compared across source costs and all surviving receiving rows. Existing receipt IDs and later sale links are preserved.
- A matching serial no longer causes the repair to skip a quantity shortage.
- Receiving reconstruction skips transfers with incomplete, missing, foreign, wrong-product, wrong-location, unreceived or future-dated source allocations. An incomplete deduction is not used to shrink an otherwise correct receiving record.
- An empty scoped transfer selection does not trigger a business-wide repair.
- Preview displays remaining invoice/SKU sent-versus-received discrepancies. Apply records the remaining discrepancies and verification status in its transactional audit log.
- Full verification is reported as passed only when both the direct quantity comparison and the reconciliation's unresolved-record list are clear.

## Verification results

**359 distinct relevant test cases passed across full runs and targeted reruns after the final correction.**

| Checks | Cases | Result |
| --- | ---: | --- |
| Stock workflows, receipt repair commands, stock purchase-cost reporting and enterprise route checks | 122 | Passed |
| Complete lot/sale consistency suite | 233 | 222 passed in the full run; 11 passed after correcting duplicate fixture column definitions |
| Backup SQL reader | 4 | Passed |

The SQLite fixture now defines `transaction_sell_lines_purchase_lines.stock_adjustment_line_id` in its shared schema, matching the application's original migration. Specialized fixture helpers no longer try to create the same column again. All 11 affected tests passed after those duplicate definitions were removed.

Five focused correction tests also passed: preserving two serials of the same SKU, updating an existing source selection together with its receiving serial, protecting receiving rows from administrator edits when later sales reference them, rejecting forced reversal of consumed receipts, and rolling back a serial mismatch even when total quantities agree. Three of these tests demonstrated the remaining bugs before the final code correction.

PHP syntax and whitespace checks passed for the changed code and tests.

Important regression cases include preview rollback, repeated repair, stale preview rejection, partial and empty receipts, repeated lots at different costs, later sales referencing multiple receipt rows, incomplete FIFO allocations, empty repair scope and rollback when audit logging fails.

Test view caches are written under `/tmp`; test logging uses stderr. Tests use isolated SQLite databases. Existing PHP 8.5 deprecation notices from database configuration are recorded separately from test failures.

Commands used:

```bash
LOG_CHANNEL=stderr VIEW_COMPILED_PATH=/tmp/stock-verify-workflow-views php -d error_reporting=22527 vendor/bin/phpunit tests/Feature --filter 'StockWorkflowRegressionTest|StockSellPurchaseCostTest|RepairTransferReceiptsCommandTest|SmartStockInventoryEnterpriseTest'
LOG_CHANNEL=stderr VIEW_COMPILED_PATH=/tmp/stock-verify-lot-views php -d error_reporting=22527 vendor/bin/phpunit tests/Feature/LotSaleConsistencyTest.php
LOG_CHANNEL=stderr php -d error_reporting=22527 vendor/bin/phpunit tests/Unit/AuthorityReceiptBackupReaderTest.php
LOG_CHANNEL=stderr VIEW_COMPILED_PATH=/tmp/stock-verify-workflow-views php -d error_reporting=22527 vendor/bin/phpunit tests/Feature/StockWorkflowRegressionTest.php --filter 'test_force_repair_|test_receiving_repair_'
```

The final lot-suite rerun selected the 11 test names reported as setup errors in the full run. Its result was `OK (11 tests, 84 assertions)`. The workflow run reported `OK (122 tests, 506 assertions)`; the initial lot run passed 222 cases with 969 assertions before the fixture errors; the backup reader reported `OK (4 tests, 9 assertions)`.

## Completing live verification

The code changes cannot establish which source receipts or deleted receiving IDs belong to the current damaged invoices. A working database connection or a current SQL export is required to inspect those records. If later movements reference deleted receiving IDs, an older verified backup or equivalent original records may also be required to recover those identities.

A live repair is complete only after the actual invoice/SKU quantities agree, connected source allocations and subsequent movements remain valid, and a fresh verification reports no unresolved records. A repair button completing by itself is not evidence of that outcome.
