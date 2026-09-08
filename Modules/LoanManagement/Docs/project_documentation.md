# Loan Management Project Documentation

## Overview

Loan Management is a Laravel module for installment sales, customer loans, loan collection, payment tracking, customer communication, public customer registration, customer self-service, reporting, and Ultimate POS integration.

Module name: `LoanManagement`  
Alias: `loanmanagement`  
Main admin URL: `/loan-management/dashboard`  
Public home URL: `/`  
Customer dashboard URL: `/customer/dashboard`  
Primary loan database connection: `mysql_loan`

The system supports two main audiences:

- Staff/admin users: manage loans, customers, payments, collections, reports, CMS, settings, Telegram chat, and import/export.
- Customers: view public website, register, log in, see loan information, schedules, payments, chats, and submit payment proof.

## Architecture

The project is structured as a Laravel module with its own routes, controllers, entities, services, migrations, views, assets, and documentation.

Important directories:

- `Routes/web.php`: browser/admin/public routes.
- `Routes/api.php`: staff mobile API, customer API, chat API, payment API.
- `Http/Controllers`: module controllers.
- `Services`: business logic services.
- `Entities`: Eloquent models for loan database tables.
- `Database/Migrations`: loan tables and compatibility migrations.
- `Resources/views`: Blade views for admin, public, customer, reports, loans, payments, chat, and settings.
- `Resources/assets/css`: module CSS.
- `Resources/assets/js`: module JavaScript.
- `Docs`: project and API documentation.

## Database

The module uses a dual-database approach:

- Main POS database: users, roles, permissions, business locations, POS contacts/products/transactions when available.
- Loan database: configured as `mysql_loan`, used for loan customers, loans, schedules, payments, chats, collections, settings, and location tracking.

Required environment keys:

```env
DB_LOAN_HOST=127.0.0.1
DB_LOAN_PORT=3306
DB_LOAN_DATABASE=loan_management
DB_LOAN_USERNAME=root
DB_LOAN_PASSWORD=
LOAN_CUSTOMER_API_DRIVER=auto
```

Core loan tables include:

- `loan_customers`
- `loans`
- `loan_items`
- `loan_payment_schedules`
- `loan_payments`
- `loan_payment_details`
- `loan_business_locations`
- `loan_collection_visits`
- `loan_chat_threads`
- `loan_chat_messages`
- `loan_chat_participants`
- `loan_customer_latest_locations`
- `loan_customer_realtime_locations`
- `loan_activity_logs`
- `loan_import_batches`
- `loan_import_rows`
- `loan_telegram_settings`
- `loan_telegram_chat_threads`
- `loan_telegram_chat_messages`

## Installation

Run the installer:

```bash
php artisan loan-management:install
```

The installer is expected to:

- Create or verify the `loan_management` database.
- Run migrations on `mysql_loan`.
- Seed permissions and reference data.
- Publish/prepare config.
- Enable the module in module status.

Uninstall:

```bash
php artisan loan-management:uninstall --force
php artisan loan-management:uninstall --force --drop-tables
```

## Permissions

Important permissions:

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

Admin routes are protected by Laravel auth/session middleware and loan activity middleware.

## Main Admin Menus

### Dashboard

URL: `/loan-management/dashboard`

Shows the loan management workspace with high-level summaries, quick search, due/payment status, and operational cards.

Main controller: `LoanDashboardController`

### New Loan

URL: `/loan-management/loans/create`

Functions:

- Create standalone loan.
- Search or create customer.
- Add product/items.
- Scan customer ID card.
- Scan product photo.
- Calculate loan schedule.
- Preview schedule.
- Save as draft, pending, or approved/active.

Main controller: `LoanCreateController`  
Main service: `CreateStandaloneLoanService`

### All Loans

URL: `/loan-management/loans`

Functions:

- List installment loans.
- Filter by date range, status, customer, phone, location, collector, and search terms.
- View loan details.
- Edit loan information, customer snapshot, items, schedule, and workflow.
- Print invoice/contract.
- Add payment.
- Change loan status.
- Delete loan where allowed.

Main controller: `LoanInstallmentListController`

### Loan Schedule

URL: `/loan-management/schedules`

Functions:

- Paginated schedule list.
- Date range filter.
- Status and loan status filters.
- Location, collector, and search filters.
- Summary cards for total, due today, open, paid, overdue, amount due, paid, and balance.
- Data table with loan number, invoice, customer, phone, installment, due date, paid date, status, principal, interest, due, paid, balance, DPD, collector, location, and actions.

Main controller: `DashboardController@loanSchedules`

### Customers

URL: `/loan-management/customers`

Functions:

- Customer list.
- Add/edit/delete customer.
- Clone customer from Ultimate POS contact.
- View customer loans and profile.
- Blacklist customer.
- Enable/disable customer app login.
- Reset customer password.
- Enable/disable GPS tracking.
- Generate Telegram connection link.
- Sync from POS contact.

Main controller: `LoanCustomerController`  
Main service: `LoanCustomerService`

### Payments

URL: `/loan-management/payments/index`

Functions:

- Payment list.
- Collapsible filters.
- Summary cards.
- View/edit/delete payment.
- Payment detail support with method, currency, exchange rate, reference number, and note.

Main controller: `LoanPaymentController`  
Main service: `LoanPaymentService`

### Collection Operations

URLs:

- `/loan-management/operations/due-today`
- `/loan-management/operations/today-collection`
- `/loan-management/operations/partial-payments`
- `/loan-management/operations/closed-accounts`
- `/loan-management/collection-visits`
- `/loan-management/overdue`

Functions:

- Due today tracking.
- Today's collection tracking.
- Partial payment tracking.
- Closed account tracking.
- Overdue account view.
- Collection visit management.
- Collapsible filters and summary UI.

Main controllers:

- `LoanCollectionController`
- `DashboardController`

### Blacklist

URL: `/loan-management/blacklist`

Functions:

- View blacklisted customers.
- Filter by date range and search.
- Summary cards for blacklisted customers and debt at risk.
- Add active customers to blacklist.
- Show linked loan counts and total debt.

Main controller: `DashboardController@blacklistIndex`

### Live Chat / Telegram Chat

URLs:

- `/loan-management/live-chat`
- `/loan-management/chat`
- `/loan-management/telegram-chat-api/chats`

Functions:

- Customer chat threads.
- Staff/customer messages.
- Telegram-linked customer messages.
- Image, document, location, and voice message support.
- Pause/resume voice recording in web UI.
- Send invoice image to customer chat.
- Preview invoice before sending.
- Custom invoice message template from Business Settings.
- File serving through `/loan-management/chat-files/{file}`.

Main controllers:

- `LoanChatController`
- `LoanTelegramChatController`
- `CustomerChatController`

Main services:

- `LoanChatService`
- `TelegramChatService`
- `LoanChatUploadService`

### Reports

#### Installment Reports

URL: `/loan-management/reports/index`

Functions:

- Professional report UI.
- Date range filter.
- Customer/location/search filters.
- Data table controls like show entries, copy, export CSV, export Excel, print, column visibility, export PDF, and search.
- Summary cards.

Main controller: `DashboardController@installmentReports`

#### Daily Loan Summary

URL: `/loan-management/reports/daily-loan-summary`

Functions:

- Date range filter.
- Current-day/default date behavior.
- Location and search filters.
- Summary cards.
- Table grouped by registered loans, paid totals, paid-off loans, and bad/risk loans.
- Empty daily rows are hidden.
- Zero values in daily table body display as blank.

Main view: `Resources/views/reports/periodic_loan_summary.blade.php`

#### Monthly Loan Summary

URL: `/loan-management/reports/monthly-loan-summary`

Functions:

- Date range filter.
- Current month as default.
- Location and search filters.
- Monthly summary cards and table.
- UI cloned from yearly summary style.

Main view: `Resources/views/reports/periodic_loan_summary.blade.php`

#### Yearly Loan Summary

URL: `/loan-management/reports/yearly-loan-summary`

Functions:

- Date range picker using one Date Range field.
- Uses Business Settings start date/financial year where applicable.
- Location and search filters.
- Summary cards.
- Yearly table grouped by registered, paid total, paid off, and bad/risk.
- Empty yearly rows are hidden.
- Zero values in yearly table body display as blank.
- Row click opens loan details.
- CSV export.

Main view: `Resources/views/reports/yearly_loan_summary.blade.php`

#### Financial Reports / Payment Summary

URLs:

- `/loan-management/reports/payments`
- `/loan-management/reports/payment-summary-by-type`

Functions:

- Payment summary by type/method.
- Date, location, and search filters.
- Professional card-based UI.

### Import / Export

URLs:

- `/loan-management/tools/import-export`
- `/loan-management/tools/loan-import-export`
- `/loan-management/tools/monthly-import-export`

Functions:

- Import loan data.
- Import payment/monthly data.
- Download templates.
- Start/process import batches.
- View progress.
- View invalid rows.
- Export loan data.

Main controller: `LoanImportExportController`  
Main service: `LoanImportExportService`

### Settings

#### Business Settings

URL: `/loan-management/settings/business`

Functions:

- Business name.
- System name and subtitle.
- Start date.
- Default profit percent.
- Currency code and symbol.
- Currency symbol placement.
- Time zone.
- Financial year start month.
- Stock accounting method.
- Transaction edit days.
- Date format.
- Time format.
- Currency precision.
- Quantity precision.
- Theme color.
- Logo upload.
- Login background upload.
- CMS enable/disable.
- Public home page text.
- Invoice message template.

Main controller: `SettingsController`  
Main service: `BusinessSettingsService`

#### CMS Settings

URL: `/loan-management/settings/cms`

Functions:

- Manage public website/home page content.
- CMS can be enabled or disabled.
- When CMS is disabled, visitors are redirected to login/admin login.
- Website button is hidden from top nav when CMS is disabled.

#### Payment Methods

URL: `/loan-management/settings/payment-methods`

Functions:

- Manage payment method options.
- Sync POS payment methods.

#### Telegram Settings

URL: `/loan-management/settings/telegram`

Functions:

- Configure Telegram bot.
- Generate webhook secret.
- Test Telegram connection.
- Register webhook.

## Public Website / CMS

Public URLs:

- `/`
- `/register`
- `/customer/login`
- `/customer/dashboard`

Functions:

- Public landing page visible without staff login when CMS is enabled.
- Product-style landing page for installment customers.
- Customer registration.
- Customer login/logout.
- Customer dashboard with loan information.
- CMS can be controlled from Business Settings.

Main controller: `PublicAppController`

## Customer App / API

Base API prefix: `/api/loan-management/customer`

Authentication guard: `customer_loan_api`

Endpoints:

- `POST /customer/login`
- `POST /customer/logout`
- `GET /customer/profile`
- `POST /customer/change-password`
- `GET /customer/dashboard`
- `GET /customer/loans`
- `GET /customer/loans/{loanId}`
- `GET /customer/loans/{loanId}/schedules`
- `GET /customer/schedules`
- `GET /customer/payments`
- `GET /customer/payments/summary`
- `POST /customer/payments/{paymentId}/proof`
- `POST /customer/upload-payment-proof`
- `POST /customer/location`
- `GET /customer/location/status`
- `POST /customer/location/enable`
- `POST /customer/location/disable`
- `GET /customer/chats`
- `POST /customer/chats`
- `GET /customer/chats/{thread}`
- `POST /customer/chats/{thread}/messages`
- `POST /customer/chats/{thread}/read`
- `POST /customer/chats/{thread}/typing`

Main controllers:

- `CustomerAppAuthController`
- `CustomerAppDashboardController`
- `CustomerAppLoanController`
- `CustomerAppPaymentController`
- `CustomerAppProfileController`
- `CustomerLocationTrackingController`
- `CustomerChatController`

More examples:

- `Docs/flutter_customer_api_examples.md`
- `Docs/flutter_chat_api_examples.md`
- `Docs/flutter_api_test.md`

## Staff Mobile API

Base API prefix: `/api/loan-management`

Authentication: `auth:api`

Endpoints:

- `POST /login`
- `POST /logout`
- `GET /profile`
- `POST /change-password`
- `GET /mobile/dashboard`
- `GET /mobile/customers`
- `GET /mobile/customers/{id}`
- `PUT /mobile/customers/{id}`
- `POST /mobile/customers/{id}/verify`
- `GET /mobile/late-customers`
- `GET /mobile/loan-form-options`
- `GET /mobile/loans`
- `POST /mobile/loans`
- `GET /mobile/loans/{loanId}`
- `PUT /mobile/loans/{loanId}`
- `DELETE /mobile/loans/{loanId}`
- `POST /mobile/loans/preview-schedule`
- `GET /mobile/loans/{loanId}/print`
- `POST /mobile/payments`
- `GET /mobile/loans/{loanId}/payments`
- `PUT /mobile/payments/{paymentId}`
- `DELETE /mobile/payments/{paymentId}`
- `POST /mobile/staff-location`
- `POST /mobile/collection-visits`
- `POST /mobile/id-card/scan`
- `POST /mobile/product-photo/scan`

Main controllers:

- `AuthController`
- `StaffMobileController`
- `StaffMobileLoanController`
- `StaffMobileActionController`

## Telegram / Messaging Flow

Customer Telegram flow:

1. Staff generates Telegram link from customer profile.
2. Customer connects Telegram.
3. Chat thread stores messages in the loan chat tables.
4. If customer is not connected, messages remain saved but are not delivered.
5. Staff can send text, image, document, location, voice, and invoice image.
6. Invoice message template is loaded from Business Settings.

Invoice sending flow:

1. Staff records payment.
2. Payment success shows invoice preview.
3. Staff clicks send invoice.
4. System creates/compresses invoice image.
5. System sends image and message to current customer chat.
6. Message appears in chat history.

## Loan Creation Flow

Standalone loan:

1. Open New Loan.
2. Select or create customer.
3. Add item/product information.
4. Enter principal, down payment, interest, term, frequency, first due date, and penalty settings.
5. Preview schedule.
6. Save loan.
7. System creates loan, loan items, payment schedules, and initial payment data if present.

POS sale loan:

1. Search existing POS sale.
2. Clone transaction/customer/product data.
3. Confirm installment terms.
4. Preview schedule.
5. Save as loan.

## Payment Flow

1. Open loan payment screen.
2. Select schedule/payment type.
3. Enter one or more payment lines.
4. Select method, amount, currency, exchange rate, reference, and note.
5. Save payment.
6. System updates loan payment records and schedule balances.
7. Invoice preview is shown.
8. Invoice can be sent to customer chat.

## Quotation Module Proposal

The project does not currently have a complete quotation module. Recommended future menu:

- `Sales / Quotations`
- `All Quotations`
- `Add Quotation`
- `Quotation Reports`
- `Quotation Settings`

Recommended quotation functions:

- Create quotation for customer.
- Add products/items.
- Add installment terms.
- Preview payment schedule.
- Print quotation.
- Send quotation image/PDF to customer chat.
- Customer accept/reject quotation.
- Convert quotation to loan application.
- Convert quotation to active loan.
- Duplicate quotation.
- Track quotation status: draft, sent, accepted, rejected, expired, converted.
- Export quotation list.
- Report quotation value and conversion rate.

Recommended tables:

- `loan_quotations`
- `loan_quotation_items`
- `loan_quotation_terms`
- `loan_quotation_status_logs`

## Main Services

- `BusinessSettingsService`: business settings, branding, CMS toggle, date/currency settings, invoice template.
- `CreateStandaloneLoanService`: standalone loan creation, schedule preview, customer resolution.
- `CreateLoanFromSellService`: convert/clone POS sale data into loan flow.
- `LoanPaymentService`: payment detail storage and POS payment method sync.
- `LoanCustomerService`: customer create/update/sync/login/GPS/Telegram helpers.
- `LoanDashboardService`: dashboard metrics.
- `LoanCollectionService`: operation and collection data.
- `LoanChatService`: internal/customer chat.
- `TelegramChatService`: Telegram chat and message relay.
- `LoanChatUploadService`: chat file upload handling.
- `LoanImportExportService`: import/export templates, batches, rows, downloads.
- `LoanCalculationService`: installment calculation support.
- `CustomerLoanSummaryService`: customer-facing loan summary.
- `CustomerLocationTrackingService`: customer GPS status and history.

## Important Console Commands

- `loan-management:install`
- `loan-management:uninstall`
- `loan-management:test-chat-schema`
- `loan-management:run-collection-automation`
- `telegram:webhook-status`
- `loan-management:import-sql-dump`

## Existing Documentation

- `Docs/chat_api.md`
- `Docs/chat_voice_message_api.md`
- `Docs/voice_message_api.md`
- `Docs/import_export.md`
- `Docs/manual_integration.md`
- `Docs/flutter_customer_api_examples.md`
- `Docs/flutter_chat_api_examples.md`
- `Docs/flutter_api_test.md`
- `Docs/final_menu_test_checklist.md`

## Recommended Test Checklist

Admin:

- Login page loads.
- CMS enabled: public home page opens.
- CMS disabled: public visitors redirect to login/admin login.
- Dashboard loads.
- New Loan can create a loan.
- All Loans filter date range works.
- Loan Schedule pagination and date range work.
- Payment create/edit/delete works.
- Payment success invoice preview appears.
- Send invoice sends image and message to current customer chat.
- Chat image/document/voice/location messages load without 404.
- Daily, Monthly, and Yearly summaries filter correctly.
- Empty Daily and Yearly summary rows are hidden.

Customer:

- Customer can register.
- Customer can login.
- Customer dashboard shows profile, loans, schedules, and payments.
- Customer can upload payment proof.
- Customer chat can send/read messages.
- Customer GPS permission/status works if enabled.

Mobile/API:

- Staff login works.
- Staff dashboard API returns data.
- Staff loan create/update/delete works.
- Staff receive payment works.
- Customer API token login/logout works.

## Development Notes

- Prefer adding business logic in `Services` and keeping controllers focused on validation/request/response.
- Prefer using `mysql_loan` for loan-domain tables.
- Check column existence with `Schema::connection('mysql_loan')->hasColumn()` when supporting multiple schema versions.
- Preserve compatibility with Ultimate POS data where POS tables exist.
- Keep report filters consistent: use one Date Range input with hidden `date_from` and `date_to`.
- Keep public CMS visibility controlled by Business Settings.
- Keep chat files served through controller routes instead of direct storage paths when files may be private or moved.

