<?php

namespace Tests\Feature;

use Modules\LoanManagement\Database\Seeders\CmsSampleDataSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;

class CmsSampleDataSeederTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'session.driver' => 'array']);
        DB::purge('mysql_loan');
        $this->storage = sys_get_temp_dir().'/cms-samples-'.bin2hex(random_bytes(5));
        $app->useStoragePath($this->storage);
        Schema::connection('mysql_loan')->create('loan_products', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('sku');
            $table->decimal('selling_price', 18, 2); $table->decimal('cost_price', 18, 2);
            $table->integer('qty_available'); $table->text('meta_json'); $table->timestamps(); $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory($this->storage);
        DB::disconnect('mysql_loan');
        parent::tearDown();
    }

    public function testFullSamplesFillEmptyContentAndCanBeRepeatedWithoutOverwritingEdits(): void
    {
        BusinessSettingsService::save(['home_cms' => ['contact_phone' => '', 'contact_address' => 'Custom Address', 'hero' => false]]);
        $seeder = new CmsSampleDataSeeder;
        $seeder->run();
        $cms = BusinessSettingsService::get()['home_cms'];
        $this->assertNotEmpty($cms['contact_phone']);
        $this->assertSame('showroom@example.com', $cms['contact_email']);
        $this->assertSame('Custom Address', $cms['contact_address']);
        $this->assertFalse($cms['hero']);
        $this->assertSame('managed', $cms['brands_source']);
        $this->assertCount(8, $cms['brands_items']);
        $this->assertNotEmpty($cms['brands_items'][4]['website_url']);
        $this->assertSame(3, DB::connection('mysql_loan')->table('loan_products')->count());
        $asus = DB::connection('mysql_loan')->table('loan_products')->where('sku', 'CMS-SAMPLE-ASUS-15')->first();
        $this->assertTrue(json_decode($asus->meta_json, true)['is_sample']);
        $this->assertSame(0, $asus->qty_available);
        DB::connection('mysql_loan')->table('loan_products')->where('id', $asus->id)->update(['selling_price' => 600, 'qty_available' => 5]);
        BusinessSettingsService::save(['home_cms' => array_merge($cms, ['contact_phone' => 'Custom Phone'])]);
        $seeder->run();
        $this->assertSame(3, DB::connection('mysql_loan')->table('loan_products')->count());
        $this->assertSame(600.0, (float) DB::connection('mysql_loan')->table('loan_products')->where('id', $asus->id)->value('selling_price'));
        $this->assertSame(5, DB::connection('mysql_loan')->table('loan_products')->where('id', $asus->id)->value('qty_available'));
        $this->assertSame('Custom Phone', BusinessSettingsService::get()['home_cms']['contact_phone']);
    }

    public function testBundledImagesExistAndResolveForSeededProducts(): void
    {
        (new CmsSampleDataSeeder)->run();
        foreach (\Modules\LoanManagement\Entities\LoanProduct::all() as $product) {
            $path = public_path($product->meta_json['image_path']);
            $this->assertFileExists($path);
            $this->assertNotFalse(getimagesize($path));
            $this->assertSame(asset($product->meta_json['image_path']), $product->image_url);
        }
        $this->assertFileExists(module_path('LoanManagement', 'Resources/assets/cms-home/hero.jpg'));
    }

    public function testOriginalRemoteSampleImagesAreUpgradedButCustomPhotosArePreserved(): void
    {
        $seeder = new CmsSampleDataSeeder;
        $seeder->run();
        $sample = CmsSampleDataSeeder::products()[0];
        $meta = $sample['meta'];
        $meta['image_path'] = $meta['image_remote_url'];
        DB::connection('mysql_loan')->table('loan_products')->where('sku', $sample['sku'])->update(['meta_json' => json_encode($meta), 'selling_price' => 600]);
        $seeder->run();
        $row = DB::connection('mysql_loan')->table('loan_products')->where('sku', $sample['sku'])->first();
        $this->assertSame($sample['meta']['image_path'], json_decode($row->meta_json, true)['image_path']);
        $this->assertSame(600.0, (float) $row->selling_price);
        $meta['image_path'] = 'https://example.com/custom.jpg';
        DB::connection('mysql_loan')->table('loan_products')->where('sku', $sample['sku'])->update(['meta_json' => json_encode($meta)]);
        $seeder->run();
        $row = DB::connection('mysql_loan')->table('loan_products')->where('sku', $sample['sku'])->first();
        $this->assertSame($meta['image_path'], json_decode($row->meta_json, true)['image_path']);
    }
}
