# KNEAYERNG DAILY FINANCIAL MANAGEMENT - DATABASE MAPPING SPECIFICATION

## 1. System Overview & Data Architecture

The **KNEAYERNG Daily Financial Management System** (`DailyFinancialManagement`) consolidates financial and operational data across three database boundaries:
1. **Primary POS Database (`ky`)**: Read-only source for sales, returns, discounts, expenses, purchases, business locations, and payment transactions.
2. **Installment / Loan Management Database (`s_loanmanagementv2`)**: Read-only source for installment agreements, customer payment schedules, principal collections, recognized interest, and penalties.
3. **Daily Financial Management Tables (in `ky`)**: High-integrity tables for financial master data, manual income/expenses, multi-tier approvals, cash/bank accounts & movements, daily closings, daily reconciliations, snapshots, and audit trails.

---

## 2. POS (`ky`) Source-to-Target Entity Mapping

### 2.1 Sales Transactions (`type = 'sell'`)
| Source Field (`ky.transactions`) | Target Field (`financial_transactions`) | Transformation / Logic |
| :--- | :--- | :--- |
| `id` | `source_id` | Foreign identifier in POS |
| `'pos_sale'` | `source_type` | Fixed source classification |
| `invoice_no` | `ref_no` | POS customer invoice number |
| `business_id` | `business_id` | Multi-business ID |
| `location_id` | `business_location_id` | POS Branch identifier |
| `transaction_date` | `transaction_date` | Date and time of sale |
| `final_total` | `amount` | Total sell amount after discount & tax |
| `'USD'` (or currency) | `currency` | Currency code |
| `exchange_rate` | `exchange_rate` | Currency exchange rate |
| `created_by` | `created_by` | POS cashier / user ID |
| `status` = `'final'` | `approval_status` = `'approved'` | Final POS sales are verified source records |
| *Calculated* | `cogs_amount` | Derived from `transaction_sell_lines_purchase_lines` * `purchase_price_inc_tax` (or `variations.dpp_inc_tax`) |

### 2.2 Sales Returns (`type = 'sell_return'`)
| Source Field (`ky.transactions`) | Target Field (`financial_transactions`) | Transformation / Logic |
| :--- | :--- | :--- |
| `id` | `source_id` | Foreign identifier in POS |
| `'pos_sell_return'` | `source_type` | Return classification |
| `return_parent_id` | `parent_id` | Links to original sale transaction |
| `invoice_no` / `ref_no` | `ref_no` | Return voucher number |
| `location_id` | `business_location_id` | POS Branch |
| `transaction_date` | `transaction_date` | Date of return |
| `final_total` | `amount` | Negative revenue / reduction in Net Sales |

### 2.3 Sales Payments (`ky.transaction_payments`)
| Source Field (`ky.transaction_payments`) | Target Field (`financial_account_movements`) | Transformation / Logic |
| :--- | :--- | :--- |
| `id` | `source_id` | Source payment ID |
| `'pos_payment'` | `source_type` | Payment classification |
| `transaction_id` | `financial_transaction_id` | Link to parent financial transaction |
| `amount` | `amount` | Cash / digital collection amount |
| `method` | `payment_method` | `cash`, `custom_pay_1` (Wing), `custom_pay_2` (ABA), `custom_pay_3` (ACLEDA), `custom_pay_6` (Trade-in), etc. |
| `paid_on` | `movement_date` | Actual cash receipt date |
| `account_id` | `financial_account_id` | Mapped to Cash / Bank financial account |

### 2.4 Purchases & Inventory (`type = 'purchase'`)
- Stored as `source_type = 'pos_purchase'`
- **Accounting Rule**: Inventory purchase payments are treated as **Cash Outflow (Investing / Working Capital)** and NOT Operating Expenses.
- Unsold inventory remains in balance sheet stock assets; only sold inventory is recognized as COGS.

---

## 3. Installment Management (`s_loanmanagementv2`) Mapping

### 3.1 Installment Payments (`s_loanmanagementv2.loan_payments`)
| Source Field (`loan_payments`) | Target Field (`financial_transactions` / `movements`) | Accounting Treatment |
| :--- | :--- | :--- |
| `id` | `source_id` | Payment reference in loan DB |
| `'loan_payment'` | `source_type` | Identifies installment record |
| `payment_ref_no` | `ref_no` | Receipt or transaction number |
| `loan_id` | `meta_json->loan_id` | Link to loan agreement |
| `amount` | Total Cash Collected | **Cash Flow Inflow** |
| `principal_paid` | `meta_json->principal_collected` | **Debt Collection (Receivable Reduction)**. Not Revenue. |
| `interest_paid` | `amount` (Income entry) | **Recognized Interest Income (P&L Revenue)** |
| `penalty_amount` | `amount` (Income entry) | **Fee / Penalty Income (P&L Revenue)** |
| `channel` | `payment_method` | `cash`, `aba`, etc. |
| `payment_date` | `transaction_date` | Payment collection date |

---

## 4. New Tables Schema Blueprint (`ky`)

### 4.1 `financial_categories`
Master chart of categories supporting hierarchy and standard financial statements.
- `id` (bigint unsigned, PK)
- `business_id` (int unsigned, FK)
- `code` (varchar 50, unique per business)
- `name_en` (varchar 191)
- `name_kh` (varchar 191)
- `type` (enum: `'income'`, `'operating_expense'`, `'cost_of_goods_sold'`, `'non_operating_income'`, `'non_operating_expense'`, `'asset'`, `'liability'`, `'equity'`)
- `parent_id` (bigint unsigned, nullable, self-referencing FK)
- `is_operating` (boolean, default true)
- `is_system` (boolean, default false)
- `is_active` (boolean, default true)
- `description` (text, nullable)
- `created_by` (int unsigned)
- Timestamps

### 4.2 `financial_accounts`
Cash registers, bank accounts, and digital wallets.
- `id` (bigint unsigned, PK)
- `business_id` (int unsigned)
- `business_location_id` (int unsigned, nullable - for branch cash registers)
- `account_code` (varchar 50)
- `account_name` (varchar 191)
- `account_type` (enum: `'cash'`, `'bank'`, `'digital_wallet'`, `'petty_cash'`)
- `account_number` (varchar 191, nullable)
- `bank_name` (varchar 191, nullable - e.g. ABA Bank, ACLEDA, Wing)
- `currency` (varchar 10, default `'USD'`)
- `opening_balance` (decimal 22,4, default 0)
- `current_balance` (decimal 22,4, default 0)
- `is_active` (boolean, default true)
- `created_by` (int unsigned)
- Timestamps

### 4.3 `financial_transactions`
Central ledger for manual income, manual expenses, and synced POS/loan summaries.
- `id` (bigint unsigned, PK)
- `business_id` (int unsigned)
- `business_location_id` (int unsigned)
- `department_id` (bigint unsigned, nullable)
- `category_id` (bigint unsigned, FK to `financial_categories`)
- `financial_account_id` (bigint unsigned, nullable, FK to `financial_accounts`)
- `transaction_type` (enum: `'income'`, `'expense'`, `'pos_sale'`, `'pos_return'`, `'loan_interest'`, `'loan_principal'`, `'transfer'`, `'adjustment'`)
- `transaction_date` (date)
- `ref_no` (varchar 191, indexed)
- `source_type` (varchar 50, default `'manual'`)
- `source_id` (bigint unsigned, nullable)
- `description` (text)
- `amount` (decimal 22,4, not null)
- `cogs_amount` (decimal 22,4, default 0)
- `currency` (varchar 10, default `'USD'`)
- `exchange_rate` (decimal 20,4, default 1.0000)
- `amount_in_base_currency` (decimal 22,4)
- `payment_method` (varchar 50)
- `approval_status` (enum: `'draft'`, `'pending'`, `'reviewed'`, `'approved'`, `'rejected'`, `'cancelled'`, `'reversed'`)
- `is_operating` (boolean, default true)
- `is_locked` (boolean, default false - set on daily closing)
- `daily_closing_id` (bigint unsigned, nullable)
- `created_by` (int unsigned)
- `reviewed_by` (int unsigned, nullable)
- `reviewed_at` (datetime, nullable)
- `approved_by` (int unsigned, nullable)
- `approved_at` (datetime, nullable)
- `reversal_of_id` (bigint unsigned, nullable)
- `meta_json` (json, nullable)
- Timestamps & SoftDeletes

### 4.4 `financial_account_movements`
Double-entry and balance tracking for every cash/bank inflow and outflow.
- `id` (bigint unsigned, PK)
- `business_id` (int unsigned)
- `financial_account_id` (bigint unsigned, FK)
- `financial_transaction_id` (bigint unsigned, nullable, FK)
- `movement_type` (enum: `'inflow'`, `'outflow'`, `'transfer_in'`, `'transfer_out'`)
- `movement_date` (date)
- `amount` (decimal 22,4)
- `balance_before` (decimal 22,4)
- `balance_after` (decimal 22,4)
- `related_account_id` (bigint unsigned, nullable - for transfers)
- `ref_no` (varchar 191)
- `notes` (text, nullable)
- `created_by` (int unsigned)
- Timestamps

### 4.5 `financial_transaction_attachments`
- `id` (bigint unsigned, PK)
- `financial_transaction_id` (bigint unsigned, FK)
- `file_path` (varchar 255)
- `file_name` (varchar 255)
- `file_type` (varchar 50)
- `file_size` (int)
- `uploaded_by` (int unsigned)
- Timestamps

### 4.6 `financial_approvals`
Audit history of approval workflow steps.
- `id` (bigint unsigned, PK)
- `financial_transaction_id` (bigint unsigned, FK)
- `action` (enum: `'submitted'`, `'reviewed'`, `'approved'`, `'rejected'`, `'cancelled'`, `'reversed'`)
- `actor_id` (int unsigned)
- `actor_role` (varchar 50)
- `comments` (text, nullable)
- `created_at` (timestamp)

### 4.7 `financial_daily_closings`
End-of-day financial locking record per branch.
- `id` (bigint unsigned, PK)
- `business_id` (int unsigned)
- `business_location_id` (int unsigned)
- `closing_date` (date)
- `closing_status` (enum: `'draft'`, `'submitted'`, `'approved'`, `'locked'`)
- `total_pos_sales` (decimal 22,4)
- `total_pos_returns` (decimal 22,4)
- `total_net_sales` (decimal 22,4)
- `total_cogs` (decimal 22,4)
- `total_gross_profit` (decimal 22,4)
- `total_manual_income` (decimal 22,4)
- `total_loan_interest` (decimal 22,4)
- `total_operating_expenses` (decimal 22,4)
- `net_profit` (decimal 22,4)
- `total_cash_inflow` (decimal 22,4)
- `total_cash_outflow` (decimal 22,4)
- `expected_cash_balance` (decimal 22,4)
- `actual_cash_counted` (decimal 22,4)
- `cash_difference` (decimal 22,4)
- `expected_bank_balance` (decimal 22,4)
- `actual_bank_balance` (decimal 22,4)
- `bank_difference` (decimal 22,4)
- `closing_notes` (text, nullable)
- `closed_by` (int unsigned)
- `approved_by` (int unsigned, nullable)
- `approved_at` (datetime, nullable)
- `locked_at` (datetime, nullable)
- Timestamps

### 4.8 `financial_reconciliations`
Denomination count and account-level reconciliation details.
- `id` (bigint unsigned, PK)
- `daily_closing_id` (bigint unsigned, FK)
- `financial_account_id` (bigint unsigned, FK)
- `reconciliation_date` (date)
- `system_balance` (decimal 22,4)
- `physical_balance` (decimal 22,4)
- `difference` (decimal 22,4)
- `denominations_json` (json, nullable - breakdown of $100, $50, $20, $10, etc., and KHR 50000, 20000, 10000)
- `notes` (text, nullable)
- `reconciled_by` (int unsigned)
- Timestamps

### 4.9 `financial_pos_imports`
Logs synchronization batches from POS and Installments.
- `id` (bigint unsigned, PK)
- `business_id` (int unsigned)
- `source_module` (varchar 50 - `'pos'`, `'loan'`)
- `sync_type` (varchar 30 - `'incremental'`, `'manual'`, `'historical'`)
- `date_from` (datetime)
- `date_to` (datetime)
- `records_processed` (int)
- `records_imported` (int)
- `records_skipped` (int)
- `records_failed` (int)
- `status` (enum: `'running'`, `'completed'`, `'failed'`)
- `error_message` (text, nullable)
- `executed_by` (int unsigned)
- Timestamps

### 4.10 `financial_audit_logs`
Immutable forensic audit trail.
- `id` (bigint unsigned, PK)
- `business_id` (int unsigned)
- `user_id` (int unsigned)
- `action` (varchar 50)
- `entity_type` (varchar 100)
- `entity_id` (bigint unsigned)
- `old_values` (json, nullable)
- `new_values` (json, nullable)
- `ip_address` (varchar 45, nullable)
- `user_agent` (text, nullable)
- `created_at` (timestamp)

---

## 5. Duplicate Prevention Strategy

1. **Composite Unique Index on Synced Transactions**:
   `UNIQUE KEY unique_source (business_id, source_type, source_id)` ensures that no POS sale, return, or loan payment can ever be imported twice.
2. **Idempotent Synchronization Service**:
   The sync service performs atomic `firstOrCreate` / `updateOrCreate` checks based on `source_type` + `source_id`.
3. **Double Counting Prevention**:
   - Manual expense creation checks whether a transaction reference belongs to a POS expense or purchase.
   - Installment down payments received at POS are flagged so the loan payment collector does not re-register the down payment as new cash.

