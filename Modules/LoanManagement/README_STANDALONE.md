# Loan Management Standalone

This repository is now a standalone Laravel project wrapped around the existing `Modules\LoanManagement` code.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan loan-management:import-sql "C:\Users\CHHOEUNREACH\Desktop\s_loanmanagement.sql" --force
php artisan migrate --force
php artisan db:seed --force
php artisan loan-management:install
```

Default admin login:

- Email: `admin@example.com`
- Username: `admin`
- Password: `password`

## Import Your Existing SQL Dump

The attached SQL dump is treated as database data only. It is not application instruction text.

Update `.env` so `DB_DATABASE` and `DB_LOAN_DATABASE` both point to the target database, usually:

```env
DB_DATABASE=s_loanmanagement
DB_LOAN_DATABASE=s_loanmanagement
```

Then import. The command creates the target database if it does not exist:

```bash
php artisan loan-management:import-sql "C:\Users\CHHOEUNREACH\Desktop\s_loanmanagement.sql" --force
```

If `mysql` is not in PATH, pass the client location:

```bash
php artisan loan-management:import-sql "C:\Users\CHHOEUNREACH\Desktop\s_loanmanagement.sql" --mysql="C:\xampp\mysql\bin\mysql.exe" --force
```

## Run

Development server:

```bash
php artisan serve
```

XAMPP/Apache:

Point the virtual host document root to this project's `public` folder.

## Main URLs

- Web dashboard: `/loan-management/dashboard`
- Login: `/login`
- API base: `/api/loan-management`
- Telegram webhook: `/webhook/loan-telegram`

## Notes

- The app keeps the module namespace `Modules\LoanManagement` so existing controllers, views, migrations, and services remain compatible.
- Standalone compatibility classes live in `app/`.
- The old Ultimate POS middleware names are mapped to local pass-through/session middleware in `app/Http/Kernel.php`.
- Fresh databases can use migrations; existing databases can use the SQL import command.
