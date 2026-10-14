# Invoice 08463: Physical Stock Verification

Database records were inspected without changing historical stock or invoices.
Invoice 08463 (transaction 27097) sold these four selected serials from location 12 on 2026-09-06 at 19:36.

| Invoice serial | Selected batch ID | Prior transfer | Transfer date | Transfer route | Later allocated serial |
| --- | --- | --- | --- | --- | --- |
| SCJJMFL25DY | 30742 | ST2026/3945 (26648) | 2026-09-06 10:23 | 12 to 18 | SF0W0C632JX |
| SDXPHYYKN2Y | 39447 | ST2026/3945 (26648) | 2026-09-06 10:23 | 12 to 18 | SGH7VW3TWPC |
| SYV677YM740 | 58982 | ST2026/3924 (26513) | 2026-09-05 19:34 | 12 to 18 | SFMGMK9X911 |
| SL7FKWRNQQR | 61275 | ST2026/3945 (26648) | 2026-09-06 10:23 | 12 to 18 | SFMGMK9X911 |

The table records allocations, not proof of which physical phones were delivered. In particular, the transfer allocation for SL7FKWRNQQR also differs from its selected batch and requires review.
Some stored serial strings contain whitespace or an invisible character; the JSON reports preserve the original strings exactly.

1. Scan the four invoice serials and the three replacement serials at locations 12 and 18.
2. Compare the scans with the signed delivery records for ST2026/3924 and ST2026/3945.
3. Check the serials actually handed to the customer on invoice 08463, using the delivery or warranty records.
4. Check invoice 08438, which explicitly selected SFMGMK9X911 on 2026-09-03.
5. Record the confirmed serial, location and supporting document before correcting a transfer, invoice or stock allocation.
6. Re-run the audit after reconciliation and verify that invoice lots, allocation quantities and location balances agree.

## Audit Commands

`php artisan stock:audit-lots 1 --invoice=08463`

`php artisan stock:audit-lots 1`

Reports are written to storage/app/stock-audits. These commands do not change database records.
The business-wide audit found 873 selected sale or transfer lines and 562 lot balances needing review. These counts identify inconsistencies, not confirmed physical shortages.
