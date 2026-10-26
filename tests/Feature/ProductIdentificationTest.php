<?php

namespace Tests\Feature;

use App\Exports\ImportProductsTemplateExport;
use App\Exports\ProductsExport;
use App\Http\Controllers\ImportProductsController;
use App\Http\Requests\MobileApi\StoreProductRequest;
use App\Http\Requests\MobileApi\UpdateProductRequest;
use App\Http\Resources\Mobile\ProductResource;
use App\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProductIdentificationTest extends TestCase
{
    private const VALUES = [
        'model_number' => 'A3526',
        'qa_number' => 'QA-001',
        'country_of_manufacture' => 'China',
        'network_connectivity' => 'SIM & E-SIM',
    ];

    public function test_migration_preserves_existing_products_and_fields_can_be_saved_cleared_and_rolled_back()
    {
        config(['database.connections.identification_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('identification_test');
        try {
            Schema::create('products', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });
            $product = Product::create(['name' => 'Existing phone']);
            $migration = require database_path('migrations/2026_10_26_000001_add_identification_fields_to_products_table.php');
            $migration->up();
            // Eloquent cached the old schema when creating the existing product.
            $columns = new \ReflectionProperty(Product::class, 'guardableColumns');
            $columns->setAccessible(true);
            $columns->setValue(null, []);
            $this->assertNull($product->fresh()->model_number);
            $product->update(self::VALUES);
            $this->assertSame(self::VALUES, Arr::only($product->fresh()->getAttributes(), array_keys(self::VALUES)));
            $product->update(['qa_number' => null]);
            $this->assertNull($product->fresh()->qa_number);
            $this->assertSame('A3526', $product->fresh()->model_number);
            $migration->down();
            $this->assertFalse(Schema::hasColumn('products', 'model_number'));
            $this->assertSame('Existing phone', $product->fresh()->name);
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('identification_test');
        }
    }

    public function test_mobile_requests_accept_optional_fields_and_reject_invalid_values()
    {
        Auth::shouldReceive('user')->andReturn((object) ['business_id' => 1]);
        foreach ([new StoreProductRequest, new UpdateProductRequest] as $request) {
            $rules = Arr::only($request->rules(), array_keys(self::VALUES));
            $this->assertCount(4, $rules);
            $this->assertTrue(Validator::make(self::VALUES, $rules)->passes());
            $this->assertTrue(Validator::make([], $rules)->passes());
            $this->assertTrue(Validator::make(array_fill_keys(array_keys(self::VALUES), null), $rules)->passes());
            foreach (array_keys(self::VALUES) as $field) {
                $this->assertTrue(Validator::make([$field => str_repeat('x', 256)], $rules)->fails());
                $this->assertTrue(Validator::make([$field => ['invalid']], $rules)->fails());
            }
        }
    }

    public function test_exports_and_template_match_and_import_accepts_both_new_and_old_files()
    {
        $product = new Product(self::VALUES + ['name' => 'Phone', 'type' => 'single']);
        foreach (['product_variations', 'product_locations', 'rack_details'] as $relation) {
            $product->setRelation($relation, collect());
        }
        foreach (['brand', 'unit', 'category', 'sub_category', 'product_tax'] as $relation) {
            $product->setRelation($relation, null);
        }
        $export = new ProductsExport;
        $row = $export->map($product);
        $this->assertSame($export->headings(), (new ImportProductsTemplateExport)->array()[0]);
        $this->assertCount(count($export->headings()), $row);
        $this->assertSame(array_values(self::VALUES), array_slice($row, 38));

        $controller = (new \ReflectionClass(ImportProductsController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'identificationFieldsFromImportRow');
        $method->setAccessible(true);
        $this->assertSame(self::VALUES, $method->invoke($controller, $row, 1));
        $this->assertSame(array_fill_keys(array_keys(self::VALUES), null), $method->invoke($controller, array_slice($row, 0, 37), 1));
        $row[38] = str_repeat('x', 256);
        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($controller, $row, 2);
    }

    public function test_product_views_compile_without_php_syntax_errors()
    {
        foreach (['create', 'edit', 'index', 'view-modal', 'partials.identification_fields', 'partials.quick_add_product', 'partials.bulk_edit_product_row', 'partials.product_list'] as $view) {
            $source = file_get_contents(resource_path('views/product/' . str_replace('.', '/', $view) . '.blade.php'));
            $compiled = app('blade.compiler')->compileString($source);
            $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
        }
    }

    public function test_form_labels_and_saved_values_are_rendered_and_escaped()
    {
        request()->headers->set('X-Requested-With', 'XMLHttpRequest');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $html = view('product.partials.identification_fields', [
            'identification_product' => new Product(self::VALUES),
        ])->render();
        foreach (['Model No.', 'QA No.', 'Made In', 'Connectivity', 'A3526', 'QA-001', 'China', 'SIM &amp; E-SIM'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $html = view('product.partials.identification_fields', [
            'identification_product' => new Product(['model_number' => '<script>alert(1)</script>']),
        ])->render();
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_purchase_identification_collapses_four_product_details_beside_the_editable_lot()
    {
        request()->headers->set('X-Requested-With', 'XMLHttpRequest');
        session(['business.enable_lot_number' => 1]);
        $data = [
            'identification_product' => (object) (self::VALUES + ['sku' => 'EXISTING-SKU']),
            'identification_row_index' => 3,
            'identification_lot_number' => 'LOT-123',
        ];
        $html = view('purchase.partials.product_identification', $data)->render();
        foreach (['Model Number', 'Reference SKU Number', 'Country of Manufacture', 'Network &amp; Connectivity', 'A3526', 'QA-001', 'China', 'SIM &amp; E-SIM'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('<details class="purchase-product-details"', $html);
        $this->assertStringNotContainsString(' open', $html);
        $this->assertSame(4, substr_count($html, 'maxlength="255"'));
        $this->assertStringNotContainsString(' readonly', $html);
        $this->assertSame(4, substr_count($html, 'name='));
        $heading = view('purchase.partials.product_heading', $data)->render();
        $this->assertStringContainsString('purchases[3][lot_number]', $heading);
        $this->assertStringContainsString('LOT-123', $heading);
        foreach (array_keys(self::VALUES) as $field) {
            $this->assertStringContainsString('name="purchases[3][' . $field . ']"', $html);
        }
        $this->assertStringNotContainsString('EXISTING-SKU', $html);
        session(['business.enable_lot_number' => 0]);
        $html = view('purchase.partials.product_identification', $data)->render();
        $this->assertStringContainsString('<details class="purchase-product-details"', $html);
        $this->assertStringNotContainsString(' open', $html);
        $this->assertSame(4, substr_count($html, 'maxlength="255"'));
        $this->assertStringNotContainsString(' readonly', $html);
        $this->assertStringNotContainsString('purchase_lot_number', $html);
        $data['identification_purchase_line'] = (object) ['model_number' => 'LOT-MODEL', 'qa_number' => '', 'country_of_manufacture' => 'Vietnam', 'network_connectivity' => 'eSIM'];
        $html = view('purchase.partials.product_identification', $data)->render();
        $this->assertStringContainsString('value="LOT-MODEL"', $html);
        $this->assertStringContainsString('value="Vietnam"', $html);
        $this->assertStringContainsString('value=""', $html);
        $this->assertStringNotContainsString('value="QA-001"', $html);
        foreach (['purchase_entry_row', 'edit_purchase_entry_row', 'product_identification', 'product_heading'] as $partial) {
            $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('views/purchase/partials/' . $partial . '.blade.php')));
            $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
        }
    }

    public function test_purchase_line_details_save_reload_clear_and_preserve_omitted_fields()
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.purchase_identification_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('purchase_identification_test');
        try {
            Schema::create('products', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('business_id');
            });
            Schema::create('variations', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('product_id');
                $table->softDeletes();
            });
            Schema::create('purchase_lines', function (Blueprint $table) {
                $table->increments('id');
                foreach (['transaction_id', 'product_id', 'variation_id', 'tax_id', 'sub_unit_id', 'purchase_order_line_id', 'purchase_requisition_line_id'] as $column) {
                    $table->integer($column)->nullable();
                }
                foreach (['quantity', 'pp_without_discount', 'discount_percent', 'purchase_price', 'purchase_price_inc_tax', 'item_tax'] as $column) {
                    $table->double($column)->default(0);
                }
                foreach (['lot_number', 'mfg_date', 'exp_date'] as $column) {
                    $table->string($column)->nullable();
                }
                $table->timestamps();
            });
            $migration = require database_path('migrations/2026_10_26_000002_add_identification_fields_to_purchase_lines_table.php');
            $migration->up();
            DB::table('products')->insert(['id' => 1, 'business_id' => 1]);
            DB::table('variations')->insert(['id' => 1, 'product_id' => 1]);
            $transaction = new \App\Transaction;
            $transaction->setRawAttributes(['id' => 7, 'business_id' => 1, 'type' => 'purchase', 'status' => 'ordered', 'exchange_rate' => 1]);
            $util = \Mockery::mock(\App\Utils\ProductUtil::class)->makePartial();
            $util->shouldReceive('updateProductStock')->andReturnNull();
            $input = self::VALUES + ['product_id' => 1, 'variation_id' => 1, 'quantity' => 1,
                'lot_number' => 'LOT-1', 'pp_without_discount' => 100, 'discount_percent' => 0,
                'purchase_price' => 100, 'purchase_price_inc_tax' => 100, 'item_tax' => 0];
            $util->createOrUpdatePurchaseLines($transaction, [$input], null, false, null, false);
            $line = \App\PurchaseLine::firstOrFail();
            $this->assertSame(self::VALUES, Arr::only($line->getAttributes(), array_keys(self::VALUES)));
            $input['purchase_line_id'] = $line->id;
            $input['model_number'] = 'NEW-MODEL';
            $input['qa_number'] = null;
            unset($input['country_of_manufacture']);
            $util->createOrUpdatePurchaseLines($transaction, [$input], null, false, 'ordered', false);
            $line = $line->fresh();
            $this->assertSame('NEW-MODEL', $line->model_number);
            $this->assertSame('', $line->qa_number);
            $this->assertSame('China', $line->country_of_manufacture);
            $this->assertSame('LOT-1', $line->lot_number);
            $migration->down();
            $this->assertFalse(Schema::hasColumn('purchase_lines', 'qa_number'));
            $this->assertSame(1, DB::table('purchase_lines')->count());
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('purchase_identification_test');
        }
    }

    public function test_mobile_response_includes_identification_fields()
    {
        $resource = new ProductResource(new Product(self::VALUES));
        $this->assertSame(self::VALUES, Arr::only($resource->toArray(request()), array_keys(self::VALUES)));
    }
}
