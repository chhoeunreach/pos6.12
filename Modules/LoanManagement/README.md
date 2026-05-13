# LoanManagement Module (Ultimate POS)

## Step 1 Scope
This package currently includes:
- Module structure under `Modules/LoanManagement`
- Dual-database architecture (`main DB` + `mysql_loan`)
- Core `mysql_loan` migrations for loan domain tables
- Loan models bound to `mysql_loan`
- Customer API guard `customer_loan_api` (auto-resolves Sanctum/Passport)
- Permission registration
- Install/Uninstall console commands

## 1. Database Configuration
Add `mysql_loan` in `config/database.php`:

```php
'mysql_loan' => [
    'driver' => 'mysql',
    'host' => env('DB_LOAN_HOST', '127.0.0.1'),
    'port' => env('DB_LOAN_PORT', '3306'),
    'database' => env('DB_LOAN_DATABASE', 'loan_management'),
    'username' => env('DB_LOAN_USERNAME', 'root'),
    'password' => env('DB_LOAN_PASSWORD', ''),
    'unix_socket' => env('DB_LOAN_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

Add env keys:

```env
DB_LOAN_HOST=127.0.0.1
DB_LOAN_PORT=3306
DB_LOAN_DATABASE=loan_management
DB_LOAN_USERNAME=root
DB_LOAN_PASSWORD=
LOAN_CUSTOMER_API_DRIVER=auto
```

`LOAN_CUSTOMER_API_DRIVER=auto`:
- uses `sanctum` if installed
- falls back to `passport` if Sanctum is not installed

## 2. Install Module
Run:

```bash
php artisan loan-management:install
```

Installer will:
- ensure `loan_management` database exists
- run module migrations on `mysql_loan`
- run module seeders
- publish module config
- register required permissions
- enable module status

## 3. Customer Guard
Customer mobile/API guard:
- Guard: `customer_loan_api`
- Provider: `loan_customers`
- Model: `Modules\LoanManagement\Entities\LoanCustomer`

Routes (module API prefix):
- `POST /loan-management/customer/login`
- `POST /loan-management/customer/logout`
- `GET /loan-management/customer/profile`
- `POST /loan-management/customer/change-password`

## 4. Required Permissions
Installer guarantees at least:
- `loan_management.view`
- `loan_management.create`
- `loan_management.edit`
- `loan_management.delete`
- `loan_management.approve`
- `loan_management.payment`
- `loan_management.report`
- `loan_management.customers.view`
- `loan_management.customers.create`
- `loan_management.chat.view`
- `loan_management.chat.reply`
- `loan_management.customer_gps.manage`

## 5. Uninstall
```bash
php artisan loan-management:uninstall --force
php artisan loan-management:uninstall --force --drop-tables
```

