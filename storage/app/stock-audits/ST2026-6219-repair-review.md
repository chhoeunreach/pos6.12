# ST2026/6219: repair review

Live database inspection and repair have not been performed. The connection was rejected for root@172.19.0.1. The observations below come from the saved audit lot-stock-1-20261014-165855.json and must be checked against current database rows before applying repairs.

## Allocation repairs identified in the saved audit

| Transfer | Line | Selected serial / batch | Existing allocation | Required allocation |
| --- | --- | --- | --- | --- |
| ST2026/5314 | 66356 | LK6YDQ50QF / 78835 | GX6Y7FM2VF / 83760 | Batch 78835 if stock history supports it |
| ST2026/6219 | 75522 | GX6Y7FM2VF / 83760 | DKJW12F07P / 88214 | Batch 83760 if stock history supports it |
| ST2026/6219 | 75525 | LT6XWFQDXD / 37519 | Unallocated / 0 | Batch 37519 if stock history supports it |
| ST2026/6219 | 75526 | JXQGDHCQ35 / 37533 | Unallocated / 0 | Batch 37533 if stock history supports it |

The selected receipt for line 66356 predates ST2026/5314; its wrong allocation points to a later receipt. The selected receipt for line 75522 also predates ST2026/6219. Fixing these allocations can resolve their receipt-date errors without changing transfer dates.

## Records that need current source history

- Lines 63104 and 64166: the plan calls their allocations unsupported. They are absent from the saved selected-line issue list, which only includes completed movements with a selected lot. Inspect their status, selected lot, allocations and return history; do not treat a FIFO allocation or a draft automatically as invalid.
- ST2026/4991: its selected LT6XWFQDXD is absent from its received serial quantities; the saved receipt uses unnamed batches. Inspect whether batches were subsequently consumed before assigning serials or splitting grouped quantities.
- ST2026/5092: its selected JXQGDHCQ35 is absent from its received serial quantities; a received batch is unnamed. Inspect downstream references and other serials before correcting it.
- Source batches 37519 (LT6XWFQDXD) and 37533 (JXQGDHCQ35): the saved history shows availability -1 while cached availability is 0. A counter reset alone cannot resolve this. Locate return transfers or another actual source receipt, or establish which earlier movement selected the wrong batch.

## Execution order once database access works

1. Read current rows for business 1, KY0021, all listed lines, transfer pairs, allocations, source and receiving batches, and all movements for the affected serials.
2. Save a before snapshot and verify that stock and audit tables support transactions.
3. Apply record-supported allocation and receiving-serial corrections in dependency order inside a transaction, logging before/after values for KY0021.
4. Recalculate affected stock counters from the corrected history; verify source and destination balances and receipt dates.
5. Re-run the correction plan for ST2026/6219. Report any genuinely unresolved shortage; do not increase receipt quantities or alter dates merely to clear warnings.
