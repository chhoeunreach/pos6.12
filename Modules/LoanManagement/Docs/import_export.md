# LoanManagement Import / Export

LoanManagement import/export data belongs to the `mysql_loan` connection and must not depend on the main Ultimate POS database for historical records.

## Import Types

- customers
- loans
- schedules
- monthly payments
- guarantors
- IMEI/serial
- collection assignments

## Import Flow

1. Download a template for the selected import type.
2. Upload Excel or CSV.
3. Map columns.
4. Preview parsed rows.
5. Validate required fields, numeric fields, dates, customer references, loan references, and duplicate keys.
6. Save a `loan_import_batches` row.
7. Save each parsed row to `loan_import_rows`.
8. Apply valid rows inside a `mysql_loan` transaction.
9. Mark failed rows with validation errors and keep the batch available for review or rollback.

## Loan CSV Columns

Required:

- `loan_number`
- `customer_id` or `customer_name`
- `principal_amount`

Supported optional columns:

- `customer_phone`
- `product_name`
- `imei_or_serial`
- `interest_amount`
- `down_payment`
- `installment_count`
- `loan_date`
- `first_due_date`
- `status`
- `currency`
- `note`

Example:

```csv
loan_number,customer_id,customer_name,customer_phone,product_name,imei_or_serial,principal_amount,interest_amount,down_payment,installment_count,loan_date,first_due_date,status,currency,note
LN-0001,,Sok Dara,012345678,iPhone 12 Pro Max,356789123456789,500.00,50.00,100.00,10,2026-05-19,2026-06-19,active,USD,Example imported loan
```

## Payment CSV Columns

Required:

- `loan_id` or `loan_number`
- `amount`
- `paid_date`

Supported optional columns:

- `schedule_id`
- `payment_method`
- `currency`
- `exchange_rate`
- `reference_number`
- `note`

When `schedule_id` is empty, the importer applies the payment to the oldest open schedule for the loan.

Example:

```csv
loan_number,schedule_id,amount,paid_date,payment_method,currency,exchange_rate,reference_number,note
LN-0001,,55.00,2026-05-19,Cash,USD,1,PAY-EXAMPLE-001,Monthly installment payment
```

## Export Types

- customers
- active loans
- overdue loans
- payment history
- monthly collections
- collector performance
- skip customers
- legal cases
- repossessions
- guarantors
- schedules

## Export Formats

- Excel
- CSV
- PDF

## Export Filters

- date range
- location
- collector
- status
- overdue bucket
- risk level
- payment method

Export requests should be logged to `loan_export_logs` with filters, format, status, file path, row count, and error message when applicable.
