# Manual Integration (Core Files Not Auto-Modified)

This module intentionally does **not** auto-edit Ultimate POS core files.

## 1) API Auth Driver Choice
If your project uses Passport, keep:
- `auth:api` for staff endpoints
- `auth:customer_loan_api` for customer endpoints

If your project uses Sanctum for API tokens, manually align guards in `config/auth.php`:
- `customer_loan_api` driver => `sanctum`
- provider => `loan_customers`

## 2) Sidebar Injection
LoanManagement sidebar is registered via module `DataController`.
If your installation has custom sidebar hooks, manually ensure module hook calls:
- `Modules\LoanManagement\Http\Controllers\DataController@modifyAdminMenu`

## 3) Storage Link (for file upload URL)
Run:
```bash
php artisan storage:link
```
So `/storage/...` URLs returned by file upload are publicly accessible.

## 4) Web Server Limits for Upload
For 10MB upload endpoint, verify php.ini:
- `upload_max_filesize >= 10M`
- `post_max_size >= 12M`

## 5) Optional Role/Permission Mapping
Module includes granular permissions and legacy fallback.
Assign role permissions manually in Ultimate POS role UI as needed.

