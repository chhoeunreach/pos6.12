# KNEAYERNG DAILY FINANCIAL MANAGEMENT - FINANCIAL CALCULATION SPECIFICATION

## 1. Accounting Framework & Core Principles

This system enforces strict standard accrual and cash accounting separation. It implements three distinct financial statements for KNEAYERNG PHONE SHOP:
1. **Profit and Loss (Income Statement)**: Accrual performance of commercial operations.
2. **Cash Flow Statement**: Liquidity movements, cash collections, supplier disbursements, and capital transfers.
3. **Cash & Bank Balance Reconciliation**: Daily proof of cash and bank account integrity.

---

## 2. Profit and Loss (Income Statement) Calculations

### Formula Hierarchy:
$$\text{Net Sales} = \text{Gross POS Sales} - \text{Sales Discounts} - \text{Sales Returns}$$

$$\text{Gross Profit} = \text{Net Sales} - \text{Cost of Goods Sold (COGS)}$$

$$\text{Recognized Other Income} = \text{Installment Interest Income} + \text{Installment Penalties} + \text{Recognized Manual Other Income}$$

$$\text{Total Operating Expenses (OPEX)} = \sum \text{Operating Expenses (Approved)}$$

$$\text{Net Operating Profit (EBIT)} = \text{Gross Profit} + \text{Recognized Other Income} - \text{Operating Expenses}$$

### Detailed Component Definitions:

#### A. Gross Sales
- Sum of all `transactions.final_total` where `type = 'sell'` and `status = 'final'` for the specified date/branch.
- Note: Tax is excluded if reporting net of tax; if tax-inclusive, net sales reflect total customer billing.

#### B. Sales Returns
- Sum of all `transactions.final_total` where `type = 'sell_return'` and `status = 'final'`.
- Deducted directly from Gross Sales.

#### C. Cost of Goods Sold (COGS)
- For every sold unit in `transaction_sell_lines`, cost is calculated:
  $$\text{COGS} = \sum (\text{Sold Quantity} \times \text{Unit Purchase Cost})$$
- Unit Purchase Cost is determined from:
  1. `transaction_sell_lines_purchase_lines.purchase_line_id` $\rightarrow$ `purchase_lines.purchase_price_inc_tax`
  2. Fallback to `variations.dpp_inc_tax` if lot tracking link is unpopulated.

#### D. Recognized Installment Interest
- Derived from `loan_payments.interest_paid` where `status = 'confirmed'`.
- **CRITICAL ACCOUNTING RULE**: Installment principal is NOT revenue. Only the earned interest portion is recognized as Interest Income.

#### E. Operating Expenses (OPEX)
- Sum of all approved expenses classified as `is_operating = 1` in `financial_transactions` or POS `transactions` where `type = 'expense'`.
- Excludes:
  - Inventory purchases (Asset / Cash outflow)
  - Asset purchases / Capital expenditures
  - Staff loan advances / Prepayments
  - Internal fund transfers between cash/bank

---

## 3. Cash Flow Calculations

Cash Flow reports the actual cash and electronic currency that entered or left the business accounts during the period, regardless of when revenue was earned.

### Formula Hierarchy:
$$\text{Total Inflow} = \text{POS Cash/Bank Collections} + \text{Loan Principal Collections} + \text{Loan Interest Collections} + \text{Customer Debt Collections} + \text{Other Cash Inflow}$$

$$\text{Total Outflow} = \text{Operating Expenses Paid} + \text{Inventory Purchases Paid} + \text{Supplier Debt Payments} + \text{Capital Expenditures} + \text{Staff Advances}$$

$$\text{Net Cash Flow} = \text{Total Inflow} - \text{Total Outflow}$$

### Inflows Breakdown:
1. **POS Cash Collections**: Payments made with `cash` method in `transaction_payments` for sales.
2. **POS Bank Collections**: Payments made with `custom_pay_2` (ABA Bank), `custom_pay_3` (ACLEDA), `custom_pay_1` (Wing), etc.
3. **Installment Principal Collected**: `loan_payments.principal_paid` (Liquidates accounts receivable, adds to cash balance).
4. **Installment Interest Collected**: `loan_payments.interest_paid` (Both cash inflow and P&L income).
5. **Loan Penalties & Fees Collected**: `loan_payments.penalty_amount`.
6. **Customer Debt Collection**: Payments on credit sales (`payment_type = 'credit_sale_payment'`).

### Outflows Breakdown:
1. **OPEX Cash Paid**: Expenses paid from cash accounts.
2. **OPEX Bank Paid**: Expenses paid via bank transfer.
3. **Inventory Purchases**: Actual purchase invoice payments to phone/accessory suppliers.
4. **Loan Advances / Outflows**: Cash disbursed for new customer loans or staff advances.
5. **Drawings / Dividends**: Owner withdrawals or equity distributions.

---

## 4. Daily Balance Reconciliation Calculations

Reconciliation verifies that physical money on hand and bank ledger balances match system expectations.

### Balance Formula:
$$\text{Expected Closing Balance} = \text{Opening Balance} + \text{Total Inflows} - \text{Total Outflows}$$

$$\text{Variance / Difference} = \text{Actual Balance Counted} - \text{Expected Closing Balance}$$

- **Surplus ($\text{Difference} > 0$)**: Physical cash exceeded recorded movements.
- **Shortage ($\text{Difference} < 0$)**: Physical cash is less than recorded movements.
- **Balanced ($\text{Difference} = 0$)**: Perfect reconciliation.

---

## 5. Currency Handling & Multi-Currency Standards

1. Primary reporting currency: **USD (\$)**
2. Secondary transactional currency: **KHR (៛)**
3. Standard exchange rate: Configurable in business settings (default e.g. 4,100 KHR = 1 USD).
4. All database monetary calculations use `DECIMAL(22, 4)`. Floating point `float` or `double` is strictly prohibited.
5. Rounding is applied only at final display formatting (`number_format($val, 2)` for USD, `number_format($val, 0)` for KHR).

