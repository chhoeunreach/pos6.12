# LoanManagement Step 1 Setup

## 1) Database connection (`config/database.php`)
Ensure this connection exists:

```php
'mysql_loan' => [
    'driver' => 'mysql',
    'host' => env('DB_LOAN_HOST', '127.0.0.1'),
    'port' => env('DB_LOAN_PORT', '3306'),
    'database' => env('DB_LOAN_DATABASE', 'loan_management'),
    'username' => env('DB_LOAN_USERNAME', 'root'),
    'password' => env('DB_LOAN_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
    'engine' => null,
],
```

## 2) Environment variables (`.env`)

```env
DB_LOAN_HOST=127.0.0.1
DB_LOAN_PORT=3306
DB_LOAN_DATABASE=loan_management
DB_LOAN_USERNAME=root
DB_LOAN_PASSWORD=
```

## 3) Auth guards (`config/auth.php`)
Configured in Step 1:
- `loan_web` (session, provider `loan_users`)
- `loan_api` (sanctum, provider `loan_users`)
- provider `loan_users` => `Modules\\LoanManagement\\Entities\\LoanUser`

## 4) Install command
Run:

```bash
php artisan loan-management:install
```

It will:
1. Check/create `loan_management` database
2. Verify `mysql_loan` connection
3. Run module migrations on `mysql_loan`
4. Run module seeders on `mysql_loan`
5. Publish module config
6. Clear caches

## 5) Uninstall command

```bash
php artisan loan-management:uninstall --force --drop-tables
```

This only affects `mysql_loan` `loan_*` tables and does not touch Ultimate POS main database.
