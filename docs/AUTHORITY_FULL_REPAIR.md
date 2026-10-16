# Administrator full Authority repair

The Authority page now provides a business-wide preview followed by an explicit apply action. It replaces the previous immediate full-fix implementation. These changes were made in the local project; no live database repair or deployment was performed.

## Using the page

1. Back up the database and validate the workflow against a database copy first.
2. Sign in as the current business's administrator (`Admin#<business ID>`) or a superadmin. An Authority permission alone does not grant access to the full-business repair.
3. Open **Smart Stock Inventory → Authority → Preview Full Business Repair**.
4. Enter the reason or audit reference and choose **Generate Full Repair Preview**. This step does not write stock records.
5. Review the exact allocation replacements, old/new selected receipt IDs, returned quantities, receipt sold counters, and location balances. Check the proposed receipt selections against the actual records.
6. Review the unresolved records. They require individual correction if receipt ownership, dates, returns, receiving serials, adjustment/manufacturing consumption, or available quantities cannot be verified.
7. Select the confirmation checkbox and choose **Apply reviewed full repair**. All proposed stock changes and their detailed audit record are saved in one database transaction.
8. If stock records changed since the preview, generate a new preview. Applied tokens cannot replay the original changes.
9. Reopen Mismatch Detector and run `php artisan stock:audit-lots BUSINESS_ID` to inspect remaining issues. Full repair reports partial progress; it does not claim that unverified shortages have disappeared.

The preview covers all locations in the current business, independently of the Authority table's reference/date filters. Normal entries during preview can invalidate its token; pause stock entry briefly when applying a large repair. MySQL stock and action-log tables must use InnoDB.

## What can be repaired

- Replace invalid quantities or duplicate mappings for a valid selected receipt when the proposed source is supported by the recorded selection and, for transfers, the receiving batches.
- Restore a missing selected receipt reference only when one complete, valid existing allocation identifies it. The proposed reference is visible for administrator review.
- Rebuild sold counters from consistent final sale/transfer allocations, including customer return quantities.
- Correct existing location balances when receipt-ledger quantities agree with Product Stock Details movement totals. Duplicate balance rows are consolidated through the existing product utility.

FIFO selections are preserved. The repair does not invent receipts, alter document quantities, change prices, rename receiving serials, change delivery dates, or erase legitimate return records. Conflicting records and missing location rows remain listed for individual correction. Unverified adjustments or manufacturing consumption block related stock releases.

## Command line

The command defaults to preview and no longer assumes business #1 or administrator #1:

```bash
php artisan stock:authority-fix-mismatches 1 --user=ADMIN_ID --reason="Verified invoice and inventory audit reference"
```

Review its JSON proposal, then use the same business, administrator and reason with the returned token:

```bash
php artisan stock:authority-fix-mismatches 1 --user=ADMIN_ID --reason="Verified invoice and inventory audit reference" --apply --token=PREVIEW_TOKEN
```

Replace the example business ID, administrator ID and token with the actual values. The command verifies the administrator's business/role. The previous service entry point also requires a preview token, so it cannot bypass review.

## Deployment

Deploy the modified Authority controller, routes, middleware, service/command files, and Authority views together, including the new `AuthorityFullRepairService.php`, `full_preview.blade.php`, and `full_preview_content.blade.php`. Clear the deployed route and view caches using the site's normal deployment process. The local tests do not verify the code or permissions deployed at pos.kytech.info.

## Validation

Passed: 48 workflow regression tests (154 assertions), 33 existing Authority tests (160 assertions), and a final rerun of all 26 full-repair tests after the application-key guard. These cover preview purity, duplicate allocations and returns, location quantity repair, stale/replayed tokens, business isolation, missing/foreign receipts, shortages, receiving serial conflicts, auxiliary consumption, administrator permissions, HTML escaping, and rollback if audit logging fails. Checks use isolated SQLite databases and a temporary view cache. Concurrent MySQL requests still require staging verification.

## Restore deleted receiving lots from an original backup

Authority now provides **Recover receiving lots** beside each transfer. This addresses receiving records that were deleted or overwritten, including the supplied ST2026/1499 case. The normal full business repair cannot invent the original IDs/serials needed by later movements.

1. In Authority, search the transfer reference and choose **Recover receiving lots**.
2. Review the sent and currently received quantities for each SKU.
3. Upload an original `.sql` export made after the transfer was created and before its receiving lots were damaged. For ST2026/1499, obtain a backup from before the recorded edits on 2026-10-10. The supplied current `ky.sql` is already missing records and is not restoration evidence.
4. Enter the repair reason, verify the source backup, and select **Preview receipt recovery and full repair**.
5. Review restored receipt IDs, quantities, serials, costs, dates and consumption; also review all business allocation/counter/balance proposals and remaining unresolved records.
6. Select **Apply reviewed receipt recovery and full repair** only after reviewing the preview. The operation restores receipt identities and runs the validated reconciliation in one database transaction. A mandatory audit failure rolls everything back.

The SQL reader extracts only the selected transfer documents and their receiving rows. It never executes uploaded SQL. Both mysqldump table-definition exports and phpMyAdmin INSERTs with explicit column names are supported. No backup is written to public storage. There is a 200 MB application upload ceiling and a 32 MB maximum individual INSERT statement; PHP/web-server upload limits also apply. For a large SQL export, the server administrator must configure `upload_max_filesize` and `post_max_size` to accommodate the file (for example 200M and 210M), and ensure sufficient request time. Existing bulk full-repair code provides request-local memory/time headroom; no persistent PHP configuration is changed by the recovery feature.

Recovery requires a true business administrator or superadmin. Ordinary Authority permission does not authorize receipt restoration. The encrypted preview is bound to the business, transfer, administrator, reason, backup content and current ledger state, including all current target receipt fields. Changes after preview require a fresh preview. CSRF protection and a separate apply confirmation remain required.

Original receipt IDs are reused, never replaced with guessed new IDs. Conflicts with another document/product/business are blocked. Existing later sales, transfers, adjustments and manufacturing allocations are checked against the recovered receipt's product, variation, business, location, dates and capacity. Counters are derived from current linked movements, not copied from a stale backup. Unknown consumption, unsupported supplier-return history, incomplete backup quantities/serials/costs, and unresolved connected source/destination movements block recovery. Other unresolved business-wide records remain explicitly listed; success messages report their remaining count.

This workflow changes receiving lot records only when original evidence supports the complete outgoing transfer. It does not change outgoing source selections or receipt dates to manufacture stock. A current damaged export cannot reconstruct missing serial identities that it no longer contains.

Validation includes a full upload/preview/encrypted-apply controller test, original-ID recovery with a later sale, actual remaining-stock reconciliation, cross-business and ID-collision rejection, stale state/actor/backup/price checks, audit rollback and SQL-reader tests for both export forms and malicious SQL expressions.

The SQL reader was exercised on the supplied 103 MB `ky.sql` (two transfer documents, six surviving receiving lots). The real-data recovery preview was also exercised against its isolated SQLite copy: it rejected the damaged backup before restoring anything, left all six receiving rows unchanged, and used approximately 393 MB peak PHP memory. This is not a live MySQL repair.

## Correct allocations without changing transfer dates

The Authority correction form now defaults to preserving both recorded document dates. Automatic receipt completion does not propose a delivery date. To repair allocations only, leave **Change delivery date** unchecked or select **Keep recorded dates**, then preview. The server ignores any stray date value when date changes are explicitly disabled, and the choice is preserved through apply.

An actual date correction requires opting in and entering its verified value. Used/referenced receiving batches remain protected: their dates cannot change through an allocation repair. A source receipt that arrived after the recorded transfer must still be corrected using an actual earlier source receipt or reviewed delivery evidence and dependent movements; this change does not bypass chronological checks.

Five focused PHP regression tests passed (22 assertions), covering an allocation preview/apply that preserves differing source/receiving dates, an explicit date change that remains blocked for used lots, a missing verified date, and existing date chronology guards. Six functional JavaScript checks passed in V8; no Node dependency was added.

## Force business repair

The Authority page also offers **Force Repair** (`ssi.authority.force_preview` / `ssi.authority.force_apply`). Unlike the reviewed repair, the force engine (`AuthorityForceRepairService`) rebuilds missing or incorrect transfer receiving serials automatically and then re-runs the reviewed engine. It runs `applyAllocationRepairs` first, then dated source re-allocation (below), then `reconcileTransferReceiving`, all inside one transaction that is rolled back on preview and only committed on a confirmed apply. Every apply writes an `authority_force_repair` audit log carrying the transfer repairs, reallocations and remaining-unresolved count.

The force repair does not invent receipts and does not change document quantities, prices or dates.

## Dated source re-allocation

A transfer line whose only defect is that it is allocated to a source receipt delivered *after* the transfer (a chronological conflict) is re-pointed to the oldest earlier receipt that was actually on hand. A candidate must share the business, source location, product and variation; be `received` with an allowed type (`purchase`, `purchase_transfer`, `opening_stock`, `production_purchase`); have a date on or before the transfer; and still carry enough free quantity (`quantity - quantity_returned - quantity_adjusted - mfg_quantity_used - committed consumption`). Receiving serials are reconciled afterwards so the destination follows the new source. Lines with adjusted or multiple mappings, missing sources, or non-transfer roots are left for individual correction.

This runs as read-only during preview. Validated against business #1: a read-only preview re-pointed 314 dated allocations and reduced unresolved records from 6062 to 3352 with no rows deleted. The reason-masking fix in `AuthorityFullRepairService::plan()` also stops an invalid transfer from overwriting a line's precise reason (for example "dated after transfer") with the generic transfer message.

`unresolvedActionLinks()`/`correctionUrl()` map `transfer-*` and `line-*` unresolved entries to the individual correction form; the reviewed and force previews render these as a **Fix** link. A focused regression test (force re-allocation preview/apply) was added and the workflow suites pass: 50 tests / 163 assertions and 36 tests / 173 assertions.

## Correction form: auto-reconcile related receiving under Allow Overselling

When **Allow Overselling** is enabled for the business, the individual Authority correction form no longer stops on a related transfer line whose receiving serial does not corroborate the line's recorded selected serial (for example `Related line #67847 still needs reconciliation: The related transfer receiving serial or quantity does not match selected serial ...`). Instead of deferring that line to a separate manual correction, the correction now completes automatically:

1. `StockTransferReconciliationService::repairRelatedSelectedAllocations()` re-points the related line to its recorded selected receipt (`lot_no_line_id`). The two serial-mismatch guards are skipped when `$allowOverselling` is on; the chronological (source predates the transfer), incomplete-movement, foreign-receipt and blank-serial guards still apply.
2. Immediately afterwards, `correctManual()` calls the scoped `AuthorityForceRepairService::reconcileReceivingForTransfers($businessId, $onlyTransferIds)` for the transfers named in the pre-correction audit's `transfer_issues` (plus the corrected transfer). Receiving rows are rebuilt in place so their serials follow the now-corrected mappings. This runs before the final audit, so neither the related-line throw nor the transfer-worsening check fires.
3. The rebuild is disclosed as `receiving_repairs` in the returned plan. Both the individual correction preview (`preview_content.blade.php`, section **Related transfer receiving rebuilt**) and the `stock_transfer_corrected` audit log show each receiving row's before → after serial and action.

With overselling **off**, behaviour is unchanged: the same mismatched related line is still blocked and must be corrected individually.

Validated read-only against business #1 transfer `35770` (`ST2026/4748`): re-pointing its five lines and reconciling left five receiving rows rebuilt in place (no warnings, no deletions, `mismatched_before` 1 → `mismatched_after` 0), reducing `transfer_issues` from 5 to 0 with no sale-line issues. Two focused regression tests were added (overselling correction auto-reconciles instead of blocking; preview discloses `receiving_repairs`); `LotSaleConsistencyTest` passes 229 tests / 1036 assertions and `StockWorkflowRegressionTest` passes 79 tests / 290 assertions.
