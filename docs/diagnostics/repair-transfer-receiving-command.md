# Repair transfer receiving lots from the command line

Use the updated `stock:authority-fix-mismatches` command with `--repair-receiving`. It uses the same receiving reconstruction and verification services as Stock Authority. The older `stock:repair-transfer-receipts` command only reduces excess receiving quantities; it skips missing receiving units.

This command processes all eligible transfers in the selected business. It requires an existing business administrator or superadmin user ID. Back up the current database before applying a reviewed repair.

## Windows Command Prompt

Open the project directory:

```bat
cd /d "C:\xampp\htdocs\apply like facebook\pos6.12"
```

If PHP is not on PATH, replace `php` in the following commands with `C:\xampp\php\php.exe` or your actual PHP executable path.

These examples use business ID `1`. Replace it if necessary, and replace `ADMIN_ID` with the administrator's actual numeric user ID. The IDs are available in the `business` and `users` database tables.

Preview without changing saved stock records:

```bat
php artisan stock:authority-fix-mismatches 1 --user=ADMIN_ID --repair-receiving --reason="Restore verified transfer receiving lots"
```

Review `transfer_repairs`, `remaining_receiving_mismatches`, `reconciliation.unresolved` and `is_fully_verified`. Copy the top-level `token`, rather than the nested reconciliation token.

Apply exactly that preview using the same business, user, reason and mode:

```bat
php artisan stock:authority-fix-mismatches 1 --user=ADMIN_ID --repair-receiving --reason="Restore verified transfer receiving lots" --apply --token=PASTE_TOP_LEVEL_TOKEN
```

An unchanged, reviewed token is required. If records changed after preview, generate and review a new preview.

## Read the result

- `Full verification passed.` and `is_fully_verified: true`: the repair services found no remaining quantity discrepancies or unresolved records.
- `Repair is incomplete.` and exit code 1: inspect the printed remaining discrepancies and unresolved reasons. Eligible changes may have been applied and audited; the remaining records still need verified source evidence or original receipt recovery.
- `Access denied`: correct the application's `.env` database credentials before retrying. After changing them, run `php artisan config:clear`.

For a separate read-only database audit:

```bat
php artisan stock:audit-lots 1
```

This prints the report path under `storage/app/stock-audits`. Refresh Product stock history after applying the repair.

## Screenshot reproduction

An isolated test reproduces SKU `10100969-14`, ST2026/5422 with seven source units but six saved receiving units, three other incoming units, three sales and seven units transferred onward under ST2026/5470. Preview leaves saved records unchanged. Apply restores the receiving quantity to seven, preserves its original ID and downstream allocation links, and reconciles the location balance from -1 to 0. This is a synthetic reproduction; it is not verification of the current live invoice.

Command and related repair tests: 30 cases / 177 assertions passed; screenshot reproduction: 1 case / 13 assertions passed. Tests cover preview-only behavior, reviewed application, stale-token rejection and a non-success result when verification remains incomplete.
