# KNEAYERNG DAILY FINANCIAL MANAGEMENT SYSTEM
## Installation and Deployment Guide

---

### 1. Overview
The **DailyFinancialManagement** module is a modular extension for UltimatePOS (Laravel 9+ / PHP 8.1+ / MySQL 8.0+) designed specifically for **KNEAYERNG PHONE SHOP**. It provides read-only synchronization from UltimatePOS and Installment Loan databases, high-integrity financial ledgering, multi-tier approval workflows, physical cash counting with denomination breakdown, daily closings, and executive financial statements.

---

### 2. File Placement & Directory Structure

Place the module inside the `Modules/` directory of your UltimatePOS codebase:

```text
/path/to/pos/Modules/DailyFinancialManagement/
├── Config/
│   ├── config.php
├── Console/
│   ├── DailyFinancialClosingCommand.php
│   └── SyncPosFinancialDataCommand.php
├── Database/
│   ├── Migrations/
│   └── Seeders/
├── Docs/
│   ├── ARCHITECTURE_GUIDE.md
│   ├── DATABASE_MAPPING.md
│   ├── FINANCIAL_CALCULATIONS.md
│   ├── INSTALLATION_GUIDE.md
│   └── USER_GUIDE.md
├── Entities/
│   ├── FinancialAccount.php
│   ├── FinancialAccountMovement.php
│   ├── FinancialApproval.php
│   ├── FinancialApprovalSetting.php
│   ├── FinancialAuditLog.php
│   ├── FinancialCategory.php
│   ├── FinancialDailyClosing.php
│   ├── FinancialPosImport.php
│   ├── FinancialReconciliation.php
│   ├── FinancialReportSnapshot.php
│   ├── FinancialTransaction.php
│   └── FinancialTransactionAttachment.php
├── Http/
│   ├── Controllers/
│   │   ├── AccountController.php
│   │   ├── ApiController.php
│   │   ├── ApprovalController.php
│   │   ├── AuditLogController.php
│   │   ├── CategoryController.php
│   │   ├── DailyClosingController.php
│   │   ├── DashboardController.php
│   │   ├── DataController.php
│   │   ├── ExpenseController.php
│   │   ├── ExportController.php
│   │   ├── IncomeController.php
│   │   ├── ReconciliationController.php
│   │   ├── ReportController.php
│   │   └── SyncController.php
│   ├── Middleware/
│   └── Requests/
├── Providers/
│   ├── DailyFinancialManagementServiceProvider.php
│   └── RouteServiceProvider.php
├── Resources/
│   ├── lang/
│   │   ├── en/lang.php
│   │   └── kh/lang.php
│   └── views/
│       ├── accounts/
│       ├── approvals/
│       ├── audit/
│       ├── category/
│       ├── closing/
│       ├── dashboard/
│       ├── expense/
│       ├── income/
│       ├── partials/
│       ├── reconciliation/
│       ├── reports/
│       └── sync/
├── Routes/
│   ├── api.php
│   └── web.php
├── Services/
│   ├── AccountMovementService.php
│   ├── ApprovalWorkflowService.php
│   ├── DailyClosingService.php
│   ├── FinancialCalculationService.php
│   └── PosFinancialSyncService.php
├── Tests/
│   ├── Feature/
│   └── Unit/
├── composer.json
└── module.json
```

---

### 3. Step-by-Step Installation

#### Step 1: Enable Module in `modules_statuses.json`
Add `"DailyFinancialManagement": true` into your root `modules_statuses.json`:

```json
{
    "DailyFinancialManagement": true
}
```

#### Step 2: Regenerate Autoloader
Run composer to ensure the new module namespace is indexed:
```bash
composer dump-autoload
```

#### Step 3: Run Database Migrations
Run the migrations targeting the module's migration path:
```bash
php artisan migrate --path=Modules/DailyFinancialManagement/Database/Migrations
```

This creates the 12 core tables in database `ky`:
- `financial_categories`
- `financial_accounts`
- `financial_daily_closings`
- `financial_transactions`
- `financial_account_movements`
- `financial_transaction_attachments`
- `financial_approvals`
- `financial_reconciliations`
- `financial_pos_imports`
- `financial_report_snapshots`
- `financial_audit_logs`
- `financial_approval_settings`

#### Step 4: Configure Loan Database Connection in `.env`
Ensure your `.env` contains the dedicated loan database connection:
```dotenv
DB_LOAN_HOST=127.0.0.1
DB_LOAN_PORT=3306
DB_LOAN_DATABASE=s_loanmanagementv2
DB_LOAN_USERNAME=root
DB_LOAN_PASSWORD=your_password
```

#### Step 5: Clear and Rebuild Laravel Caches
```bash
php artisan optimize:clear
```

#### Step 6: Initial POS Financial Sync
Run the initial sync command to populate historical transactions:
```bash
php artisan financial:sync-pos --business-id=1
```

---

### 4. Background Scheduling (Cron Jobs)

Add the following to your server's crontab (`crontab -e`) to automate synchronizations and daily closings:

```cron
# Run standard Laravel scheduler
* * * * * cd /var/www/pos6.12 && php artisan schedule:run >> /dev/null 2>&1

# Alternatively, schedule sync directly every 30 minutes:
*/30 * * * * cd /var/www/pos6.12 && php artisan financial:sync-pos --business-id=1 >> /var/www/pos6.12/storage/logs/financial_sync.log 2>&1

# Automatic daily closing calculation at 23:55 PM:
55 23 * * * cd /var/www/pos6.12 && php artisan financial:daily-closing --business-id=1 >> /var/www/pos6.12/storage/logs/daily_closing.log 2>&1
```

---

### 5. Running Automated Tests

Run the test suite to verify calculation accuracy, double-counting prevention, and workflow transitions:

```bash
./vendor/bin/phpunit Modules/DailyFinancialManagement/Tests/Unit
```
Expected output:
```text
OK (6 tests, 15 assertions)
```

