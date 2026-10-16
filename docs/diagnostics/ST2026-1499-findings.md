# ST2026/1499 — findings from ky.sql

This is an analysis of the supplied export, inspected using an isolated SQLite copy. The original export and running MySQL database were not modified. Findings describe the export snapshot, not later database activity.

## Confirmed mismatch

- Business 1, outgoing transaction **11193**, receiving transaction **11194**.
- Transfer date: **2026-07-30 11:13:00**; source location **12**, destination **1**.
- Product **2488** is variable. SKU **10100793-23** is variation **9129**.
- That variation has **10 outgoing rows / 10 units**, but only **1 receiving row / 1 unit** (receipt **22668**, serial **FJP9XHFYHY**). Therefore its current history entry of +1 accurately reflects the damaged receiving records. Eleven is not supported as the outgoing quantity for this SKU by this export.
- Across all products, the transfer has **31 outgoing units**, but **6 receiving units**, a deficit of **25 receiving units**.
- All 31 outgoing rows have matching source allocations, received source transactions in the same business/location, and receipt dates before the transfer. Missing destination serials can be identified from these surviving source rows.

| SKU | Outgoing units | Receiving units | Deficit |
|---|---:|---:|---:|
| 10100715-21 | 4 | 1 | 3 |
| 10100715-23 | 1 | 1 | 0 |
| 10100715-24 | 3 | 1 | 2 |
| 10100757-24 | 12 | 1 | 11 |
| 10100793-23 | 10 | 1 | 9 |
| 10100799-23 | 1 | 1 | 0 |

## Why adding stock or recreating nine new receipt IDs is insufficient

Seven sales for variation 9129 still select and allocate against deleted receiving receipt IDs. The existing receiving row has also been edited. Creating fresh receipt IDs without restoring the old identities would leave these sales disconnected and could count sold units as available.

| Missing receipt ID | Sale transaction | Invoice | Quantity | Surviving serial note |
|---|---:|---|---:|---|
| 22669 | 11336 | 3245 | 1 | (none) |
| 22670 | 12349 | 3633 | 1 | (none) |
| 22671 | 11820 | 3403 | 1 | DF73QDW66G |
| 22673 | 12661 | 3784 | 1 | (none) |
| 22674 | 12365 | 3639 | 1 | (none) |
| 22676 | 11975 | 3467 | 1 | (none) |
| 22678 | 12080 | 3516 | 1 | (none) |

Sale line **16368** (invoice **3403**) preserves serial **DF73QDW66G** in its note, which corroborates receipt ID **22671**. The other six missing sale-receipt identities are not established by serial evidence in this export. Do not infer them solely from sequential IDs or row order.

The six surviving receiving rows were last updated at **2026-10-10 17:06:00**. Activity log records **42976** and **42977** record edits to transfer 11193 at **17:04:22** and **17:06:00** on that date. This supports investigating those edits; it does not by itself prove which application version deleted each row. No original line snapshots were found in these activity entries.

## Local code changes and checks

- Stock history sums lots per transaction and variation.
- Viewing history does not rewrite saved location stock.
- History now flags received transfer quantities that disagree with the matching completed source transfer. This export produces **Sent: 10, Received: 1** for variation 9129, without inventing received units.
- A full transfer test of eleven one-unit lots preserves all lots and moves eleven units using the shared transfer service. All 54 workflow tests passed (194 assertions); the extended missing-receipt check subsequently passed with 12 assertions, including completely absent receiving rows and business scoping.
- The current browser transfer edit flow preserves receipt rows for metadata-only edits and refuses to replace receiving lots already used by subsequent movements.

## Required evidence for historical restoration

Obtain a database backup from **before 2026-10-10 17:04:22**, after this transfer was created on 2026-07-30. Restore the original receiving rows and serial identities from that backup into a separate working copy, preserving receipt IDs referenced by later sales and transfers. Compare against current source selections, later allocations, returns, adjustments, costs and dates before preparing a transactionally guarded repair. Rebuild receipt counters from valid linked movements, then reconcile location balances. Preview and verify all changes before applying them to live MySQL.

Without those original identities (or equivalent verified original serial documents), a complete serial-preserving repair cannot be established from this export alone. No executable data-changing SQL was generated, and no live repair was applied.

The current saved stock of **22** at location 1 is also the final history balance computed from the damaged records. This agreement does not make the deleted receipt history correct. Do not set current stock to the transfer quantity or blindly add the missing transfer quantity without restoring and verifying the connected movements.

## Artifacts

- `ST2026-1499.sql`: read-only source/destination comparison.
- `ST2026-1499-missing-receiving-lots.csv`: 25 source-backed serials absent from the current receiving document; this list does not assign replacement receipt IDs.
- `ky-transfer-quantity-mismatches.csv`: nine completed transfer pairs in business 1 with different outgoing and receiving totals. This count excludes possible equal-total serial or allocation mismatches.
