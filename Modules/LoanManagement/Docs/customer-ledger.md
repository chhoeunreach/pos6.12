# Customer ledger

Open **Customers → Action (three dots) → Ledger**, or select **Ledger** from Customer Detail. The statement opens in the customer modal and is also available at `/loan-management/customers/{customer}/ledger`.

The ledger is read-only. Filters include inclusive From/To dates, installment, and currency. The opening balance includes movements before the selected start date. Balances run independently by currency; totals never add USD and KHR together. Print/PDF opens a printable statement suitable for the browser's Save as PDF. CSV export requires `loan_management.export.view` or `view_export_buttons`.

## Calculation basis

- Current scheduled contract total plus the recorded upfront deposit creates the installment charge. Posted parent payment rows create credits, so a Cash/ABA split is counted once. Foreign receipts use their recorded base amount and the installment's recorded exchange rate when conversion is needed.
- Draft, pending, cancelled, and rejected contracts do not create charges. Their posted receipts still appear as customer credits. Failed, voided, pending, draft, rejected, cancelled, and soft-deleted payment records are excluded. Soft-deleted installments are excluded.
- The statement uses current records, not an immutable historical journal. When current stored balances differ from charges minus receipts (for example imported balances, changed terms, waived charges or accrued penalties), a visibly marked **Recorded balance adjustment** reconciles the difference. This is a computed statement line, never an extra payment or database write. It is dated at the latest of the loan date, last posted receipt, and loan update date. Historical filters apply to that date. The ledger explains this basis in both the screen and printed statement.
- Undated records appear in an unfiltered statement. Date filters exclude them and show their excluded count.
- A negative closing balance represents a customer credit.

The module's customer-view and financial-view permissions protect the ledger. Its **POS Ledger** shortcut appears only when the customer has a linked `main_contact_id`, the contact belongs to the current POS business, and the viewer has POS customer-view permission. The destination retains its own contact-access checks. Standalone loan customers do not require a POS contact.

Backend checks use isolated SQLite databases and include deposits, split payments, date openings, multiple currencies, explicit adjustments, excluded transactions, customer scope, export permissions/formula escaping, printable views, and POS-link business boundaries. Simulated browser checks cover modal filtering and retained filters after validation failure.
