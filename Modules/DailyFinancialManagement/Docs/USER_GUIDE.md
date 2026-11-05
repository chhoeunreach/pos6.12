# KNEAYERNG DAILY FINANCIAL MANAGEMENT SYSTEM
## User and Operations Guide

---

### 1. Introduction & User Roles

This guide explains how to use the Daily Financial Management System across all authorized roles:

| Role | Responsibilities | Key Actions |
| :--- | :--- | :--- |
| **Business Owner / Director** | High-level financial visibility, final approval for large capital expenditures, locking monthly periods | Executive Dashboard, P&L, Cash Flow, High-limit approvals |
| **General Manager** | Branch oversight, operational performance review | Branch comparison, approvals, audit review |
| **Accountant** | Accounting validation, category mapping, transaction review, bank statement reconciliation | Review queue, category setup, bank reconciliation |
| **Branch Manager** | Daily closing verification, branch approvals, physical cash sign-off | Daily closing wizard, expense approval |
| **Cashier** | Day-to-day cash handling, physical banknote counting, recording local cash expenses | Record expense/income, denomination counting |

---

### 2. Main Modules & Workflows

#### 2.1 Executive Financial Dashboard
- **Location in Menu**: *Daily Financial Management &rarr; Dashboard*
- **KPI Summary**:
  - **Net Sales**: Gross sales minus discounts and returns.
  - **COGS**: True cost of items sold, linked to lot purchase records.
  - **Gross Profit**: Net Sales minus COGS.
  - **Total OPEX**: Approved operating expenses.
  - **Net Operating Profit**: Gross profit + recognized other income - OPEX.
  - **Cash & Bank Balances**: Real-time liquid asset reserves.
- **Cash Flow vs Accounting Profit Separation**:
  - The dashboard explicitly highlights that **loan principal collections** and **inventory purchases** are cash liquidity movements, NOT commercial revenues or expenses.

#### 2.2 Income Management
- **Recording Income**:
  1. Click **Record Income**.
  2. Select Branch, Department, Category, and Deposit Account.
  3. Enter Amount, Currency (USD or KHR), and Payment Method (Cash, ABA Bank, ACLEDA, Wing).
  4. Upload attachment (deposit slip / voucher).
  5. Choose **Save as Draft** or **Save & Submit for Approval**.

#### 2.3 Expense Management
- **Recording Expense**:
  1. Click **Record Expense**.
  2. Select Branch and Department.
  3. Choose Category (e.g., Rent, Utilities, Salaries, Logistics, Office Supplies).
  4. Select Pay From Account (Cash drawer or Bank account).
  5. Check **Classify as Operating Expense (OPEX)** if it is an operating cost; uncheck if it is an asset purchase or advance.
  6. Upload receipt/invoice image.
  7. Submit for approval.

#### 2.4 Multi-Tier Approval Workflow
- Transactions pass through a four-stage lifecycle:
  ```text
  [Draft] ──> [Pending] ──> [Reviewed by Accountant] ──> [Approved by Manager]
                 │                    │
                 └───> [Rejected] <───┘
  ```
- **Approved Transactions are Immutable**: They cannot be deleted or silently edited.
- **Reversal Workflow**: If an approved transaction requires correction, click **Reverse Transaction**. Enter the reason to generate an offsetting reversal record with a full audit log.

#### 2.5 Daily Closing Wizard
- Execute at the end of each business day:
  - **Step 1: Check Pre-closing status**: Verify that all POS sales are synced and all pending expenses are approved.
  - **Step 2: Banknote Counting**: Use the interactive denomination calculator:
    - USD: $100, $50, $20, $10, $5, $1 notes.
    - Khmer Riel: 50,000, 20,000, 10,000, 5,000, 2,000, 1,000 notes (auto-converted to USD at 4,100).
  - **Step 3: Bank Confirmation**: Verify ABA and ACLEDA bank balances against statements.
  - **Step 4: Variance Review**: Review surplus/shortage and enter explanations.
  - **Step 5: Lock Period**: Manager clicks **Lock Financial Period** to permanently freeze transactions for that date.

#### 2.6 Daily Cash & Bank Reconciliation
- View system expected balance vs physical balance.
- Track historical variances (surplus/shortage).

#### 2.7 Reports & Exports
- **Daily Executive Report**: Comprehensive summary with P&L, Cash Flow, and Liquid Balances.
- **Profit & Loss Report**: Full standard accounting income statement.
- **Cash Flow Statement**: Inflow and Outflow analysis.
- **Branch Comparison**: Multi-branch KPI benchmarking.
- **Exports**: Click **Export Excel** for CSV/XLS or **Download PDF** for high-resolution printable reports.

#### 2.8 Forensic Audit Trail
- Every transaction create, update, approval, rejection, reversal, and sync is logged with user, IP, timestamp, old state, and new state.

