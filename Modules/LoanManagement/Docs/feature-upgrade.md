# Reference feature integration

The POS module now includes the reference project's quotation workflow, conversion to loans, settlement, restructuring, promise-to-pay records, loan contracts, printable receipts, PAR reporting, CBC export, expanded business/payment/CMS settings, and customer portal document uploads. Existing POS authentication, stock integration, split payment editing, and installment table totals remain in use.

## Database activation

From the POS application directory, using its working `mysql_loan` database connection:

```sh
php artisan loan-management:upgrade-features --pretend
php artisan loan-management:upgrade-features
```

This runs only the four additive feature migrations. It does not reinstall the module, seed demonstration accounts/products, or automatically charge penalties. The command can be run again safely after completion.

If an earlier deployment has cached routes or views, refresh them through the application's normal deployment process.

## Access

New menu items use existing loan view, creation, editing, approval, collection and reporting permissions. CBC downloads also require `loan_management.export.view`. Settlement and restructuring require approval permission.

The reference's ABA PayWay checkout is unconfigured. Its simulated payments cannot post to the ledger. Live bank processing needs a separately configured and verified integration.

Penalty accrual is available as an explicit command and is not scheduled automatically:

```sh
php artisan loan-management:accrue-penalties --dry-run
```
