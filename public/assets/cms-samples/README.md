# CMS Sample Product Images

These manufacturer images accompany the sample catalog, not actual stock or pricing.
Brand names and product photos belong to their respective owners; attribution does
not grant redistribution or commercial-use rights. Replace samples with your own
authorized product photography for production use.

- `asus-vivobook-15.jpg`: ASUS Vivobook 15, https://www.asus.com/es/laptops/for-home/vivobook/vivobook-15-m1502/
- `canon-pixma-ts3440.png`: Canon PIXMA TS3440, https://en.canon-cna.com/support/consumer/products/printers/pixma/ts-series/pixma-ts3440.html
- `canon-pixma-mg2550s.png`: Canon PIXMA MG2550S, https://www.canon.ru/printers/home-printers/

Run `php artisan db:seed --class=CmsSampleDataSeeder` after migrations to create
the sample catalog. Repeated runs preserve edited records and only replace the
original remote sample photo URLs with bundled files.

The default homepage background is tracked at
`Resources/assets/cms-home/hero.jpg` and served through `/cms/home-image`.
Custom uploaded backgrounds remain in application storage, not Git.
