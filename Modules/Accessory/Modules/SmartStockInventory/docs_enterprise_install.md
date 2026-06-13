# SmartStockInventory Enterprise Inventory Count - Install Guide

## Requirements
- Ultimate POS v6.12
- SmartStockInventory module enabled
- PHP extension for camera scanner depends on browser (BarcodeDetector API for mobile)

## 1) Run migrations
```bash
php artisan migrate
```

## 2) Assign permissions
Grant to roles as needed:
- stock_inventory.update
- stock_inventory.delete
- stock_inventory.fix
- stock_inventory.rollback
- stock_inventory.logs
- stock_inventory.approve
- stock_inventory.recount
- stock_inventory.verify
- stock_inventory.mobile
- stock_inventory.freeze
- stock_inventory.report
- stock_inventory.adjust

## 3) Open pages
- Enterprise Count: `/smart-stock-inventory/count/enterprise`
- Mobile Count: `/smart-stock-inventory/count/enterprise/session/{id}/mobile`
- Fix Logs: `/smart-stock-inventory/fix-logs`
- Reports: `/smart-stock-inventory/count/reports`

## 4) Workflow
1. Create session (count type/method/by)
2. Assign counters
3. Count lines (manual/barcode/IMEI)
4. Verify lines
5. Recount when threshold exceeded
6. Supervisor/manager approval
7. Adjustment preview and export
8. Finalize

## 5) Notes
- Blind count hides system qty in enterprise/mobile mode behavior.
- Offline draft uses browser localStorage.
- Freeze log is recorded and can be enforced by custom sale guard middleware.
- Telegram alerts fire for recount-required and manager approval events.