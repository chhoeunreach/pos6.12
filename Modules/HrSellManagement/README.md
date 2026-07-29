# HR Sell Management

HR Sell Management is an UltimatePOS module for managing sales assigned to HR/staff without replacing the POS sales engine.

## Features

- Link existing POS sell transactions to HR staff.
- Track HR staff, supervisor, status, approval status, commission, due amount, follow-up date, and internal notes.
- Approval workflow with configurable levels.
- Follow-up notes for call, visit, issue, and promise tracking.
- Dashboard KPIs for sales, due, commission, pending approvals, and due follow-ups.
- HR staff performance and sales reports.
- Excel export for HR sell reports.
- Activity log for changes, notes, and approvals.

## Tables

- `hr_sell_records`
- `hr_sell_notes`
- `hr_sell_approvals`
- `hr_sell_logs`
- `hr_sell_settings`

The module reuses UltimatePOS `transactions`, `transaction_sell_lines`, `transaction_payments`, `contacts`, `products`, and `users`.
