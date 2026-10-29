<?php

namespace Modules\LoanManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Modules\LoanManagement\Services\CmsHomeService;

class CmsSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSettingsService::seedCmsDefaults();
        $settings = BusinessSettingsService::get();
        $cms = $settings['home_cms'];
        foreach (CmsHomeService::sampleDefaults() as $key => $value) {
            if (is_string($value) && trim((string) ($cms[$key] ?? '')) === '') {
                $cms[$key] = $value;
            }
        }
        $sites = [
            'PREDATOR' => 'https://www.acer.com/', 'Microsoft' => 'https://www.microsoft.com/',
            'Lenovo' => 'https://www.lenovo.com/', 'HIKVISION' => 'https://www.hikvision.com/',
            'ASUS' => 'https://www.asus.com/', 'EPSON' => 'https://www.epson.com/',
            'Canon' => 'https://www.canon.com/', 'TOSHIBA' => 'https://www.toshiba.com/',
        ];
        foreach ($cms['brands_items'] as &$brand) {
            if ($brand['website_url'] === '') $brand['website_url'] = $sites[$brand['name']] ?? '';
        }
        unset($brand);
        if ($cms['brands_source'] === 'catalog' && array_column($cms['brands_items'], 'name') === array_column(CmsHomeService::sampleBrands(), 'name')) {
            $cms['brands_source'] = 'managed';
        }
        foreach (['footer_facebook' => 'https://www.facebook.com/', 'footer_telegram' => 'https://t.me/',
            'footer_instagram' => 'https://www.instagram.com/', 'footer_tiktok' => 'https://www.tiktok.com/',
            'footer_youtube' => 'https://www.youtube.com/', 'footer_website' => config('app.url')] as $key => $value) {
            if ($cms[$key] === '') $cms[$key] = $value;
        }
        BusinessSettingsService::save(array_merge($settings, ['home_cms' => $cms]));

        if (!Schema::connection('mysql_loan')->hasTable('loan_products')) {
            throw new \RuntimeException('The loan_products table is missing. Run the application migrations before seeding catalog samples.');
        }
        $columns = array_flip(Schema::connection('mysql_loan')->getColumnListing('loan_products'));
        DB::connection('mysql_loan')->transaction(function () use ($columns) {
            foreach (self::products() as $product) {
                $meta = $product['meta'];
                $existing = DB::connection('mysql_loan')->table('loan_products')->where('sku', $product['sku'])->first();
                if ($existing) {
                    $savedMeta = json_decode($existing->meta_json ?? '{}', true);
                    if (is_array($savedMeta) && ($savedMeta['is_sample'] ?? false) && ($savedMeta['image_path'] ?? '') === $meta['image_remote_url']) {
                        $savedMeta['image_path'] = $meta['image_path'];
                        DB::connection('mysql_loan')->table('loan_products')->where('id', $existing->id)->update([
                            'meta_json' => json_encode($savedMeta), 'updated_at' => now(),
                        ]);
                    }
                    continue;
                }
                unset($product['meta']);
                DB::connection('mysql_loan')->table('loan_products')->insert(array_intersect_key(array_merge($product, [
                    'cost_price' => 0, 'qty_available' => 0,
                    'meta_json' => json_encode($meta), 'created_at' => now(), 'updated_at' => now(),
                ]), $columns));
            }
        });
        $this->command?->info('Sample CMS contact information, brand links, and catalog products seeded. Existing records preserved.');
    }

    public static function products(): array
    {
        return [
            [
                'sku' => 'CMS-SAMPLE-ASUS-15', 'name' => 'ASUS Vivobook 15', 'selling_price' => 520,
                'meta' => ['brand' => 'ASUS', 'model' => 'Vivobook 15', 'category' => 'Computers', 'is_sample' => true,
                    'description' => 'Sample catalog item for installment requests. Sample price; staff confirms specifications, stock, and final terms.',
                    'image_path' => 'assets/cms-samples/asus-vivobook-15.jpg',
                    'image_remote_url' => 'https://dlcdnwebimgs.asus.com/gain/8605ff12-0f86-4a5e-9ddc-1241e6f9827a/',
                    'image_source' => 'https://www.asus.com/es/laptops/for-home/vivobook/vivobook-15-m1502/'],
            ],
            [
                'sku' => 'CMS-SAMPLE-CANON-TS3440', 'name' => 'Canon PIXMA TS3440', 'selling_price' => 180,
                'meta' => ['brand' => 'Canon', 'model' => 'PIXMA TS3440', 'category' => 'Printers', 'is_sample' => true,
                    'description' => 'Sample printer catalog item. Sample price; staff confirms stock and installment terms.',
                    'image_path' => 'assets/cms-samples/canon-pixma-ts3440.png',
                    'image_remote_url' => 'https://i1.adis.ws/i/canon/uxmal-mea-bk-fra_range_7bbaa46ceee1458aa0f4566622506fa0',
                    'image_source' => 'https://en.canon-cna.com/support/consumer/products/printers/pixma/ts-series/pixma-ts3440.html'],
            ],
            [
                'sku' => 'CMS-SAMPLE-CANON-MG2550S', 'name' => 'Canon PIXMA MG2550S', 'selling_price' => 95,
                'meta' => ['brand' => 'Canon', 'model' => 'PIXMA MG2550S', 'category' => 'Printers', 'is_sample' => true,
                    'description' => 'Sample home printer catalog item. Sample price; staff confirms stock and installment terms.',
                    'image_path' => 'assets/cms-samples/canon-pixma-mg2550s.png',
                    'image_remote_url' => 'https://cdn.media.amplience.net/i/canon/pixma-mg2550s-closed-bk-frt_a91d-b083fea00e8e?h=800&w=800',
                    'image_source' => 'https://www.canon.ru/printers/home-printers/'],
            ],
        ];
    }
}
