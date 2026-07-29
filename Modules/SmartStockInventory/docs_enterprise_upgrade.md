# Smart Stock Inventory Enterprise Upgrade

This upgrade adds an enterprise audit layer beside UltimatePOS inventory. It does not replace Purchase, Sell, Transfer, Adjustment, product, variation, or stock balance tables. The module reads UltimatePOS inventory and transaction data as the source of truth, then stores audit workflow data in `ssi_` tables only.

## ERD

- `ssi_audits`
  - Audit session header: cycle, blind, spot, full, recount, scheduling, scope, status.
- `ssi_audit_items`
  - Expected vs counted stock by product variation, IMEI, serial, lot, warehouse, zone, rack, shelf, bin.
- `ssi_audit_scans`
  - Mobile scanner events with device, user, scan value, quantity, and warehouse coordinates.
- `ssi_investigations`
  - Missing IMEI, extra IMEI, wrong warehouse, wrong shelf, wrong transfer, lost, damaged, warranty, repair, notes, attachments.
- `ssi_approvals`
  - Counter -> Supervisor -> Warehouse Manager -> Inventory Manager -> General Manager.
- `ssi_logs`
  - Activity, audit, scan, and approval logs.
- `ssi_dashboard_cache`
  - Cached dashboard payloads for heavy stock metrics.
- `ssi_settings`
  - Business-level SSI enterprise settings.

## Implemented Slice

- New `ssi_` schema with indexes for 100K+ products and 500K+ IMEI lookup patterns.
- Enterprise models under `Modules\SmartStockInventory\Models\Enterprise`.
- Repository/service layer:
  - `SsiAuditRepository`
  - `SsiAuditService`
  - `SsiScannerService`
  - `SsiDashboardService`
  - `SsiLogService`
- Controllers:
  - Audit lifecycle
  - Mobile scanner JSON endpoint
  - Enterprise report dashboard
- Views:
  - Audit session list/dashboard
  - Audit detail with verification, approvals, investigations, logs
  - Mobile scanner UI
  - Difference report
- Permission names enforced:
  - `ssi.audit.view`
  - `ssi.audit.create`
  - `ssi.audit.update`
  - `ssi.audit.scan`
  - `ssi.audit.verify`
  - `ssi.audit.investigate`
  - `ssi.audit.approve`
  - `ssi.audit.adjust`
  - `ssi.audit.report`
  - `ssi.audit.settings`

## Compatibility Notes

The current repo is Laravel 9 and PHP `^8.0`; the implementation avoids Laravel 12-only APIs so it works in this installation and remains forward-migration friendly.

Permissions are not inserted by the enterprise migration because this upgrade follows the requirement that database changes create only new `ssi_` tables and never modify UltimatePOS core tables.

## Next Phases

1. Queue jobs for full stock snapshot seeding and dashboard cache warmup.
2. Add export jobs for KPI, aging, valuation, and warehouse reports.
3. Register policies in the module provider when the host app policy map is ready.
4. Add stock adjustment orchestration that creates normal UltimatePOS Adjustment transactions only after General Manager approval.
