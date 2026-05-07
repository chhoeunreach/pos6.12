# ClearDataByDate (UltimatePOS module)

Deletes business transactions in a selected date range **safely** (preview first, confirm before delete, and logs every run).

## Install (UltimatePOS v6.12)

1. Copy the whole folder to: `Modules/ClearDataByDate`
2. Enable the module:
   - Add `"ClearDataByDate": true` in `modules_statuses.json` (project root), **or**
   - Run: `php artisan module:enable ClearDataByDate`
3. Autoload + caches:
   - `composer dump-autoload`
   - `php artisan cache:clear`
   - `php artisan config:clear`
4. Run migrations:
   - `php artisan module:migrate ClearDataByDate`
5. Assign permission to admin roles:
   - `clear_data_by_date.access` (or `clear_data_by_date.view`)

## Usage

- Open: `Settings → Clear Data by Date` (menu added by the module).
- Click **Preview** first (shows counts).
- To delete, type `DELETE` and enter your password.
- Optional: **Dry-run** logs only (no deletions).

## Safety notes

- Always take a full DB backup before deleting.
- Deletes are always scoped by `business_id` and (optionally) `location_id`.
- Master data is never deleted (users/products/categories/brands/units/tax rates/settings).

