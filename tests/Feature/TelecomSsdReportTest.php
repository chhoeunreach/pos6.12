<?php

namespace Tests\Feature;

use App\Http\Controllers\TelecomSsdReportController;
use App\Services\Reports\TelecomSsdReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TelecomSsdReportTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.telecom_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('telecom_test');
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id');
            $table->integer('brand_id');
            $table->integer('category_id');
            $table->string('name');
            foreach (['model_number', 'qa_number', 'country_of_manufacture', 'network_connectivity'] as $field) {
                $table->string($field)->nullable();
            }
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id');
            $table->string('name');
        });
        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id');
            $table->string('name');
            $table->string('supplier_business_name')->nullable();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            foreach (['business_id', 'location_id', 'contact_id'] as $field) {
                $table->integer($field);
            }
            foreach (['type', 'status', 'transaction_date', 'additional_notes'] as $field) {
                $table->string($field);
            }
        });
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transaction_id');
            $table->integer('product_id');
            $table->double('quantity');
            $table->double('quantity_returned')->default(0);
            $table->double('purchase_price_inc_tax');
            foreach (['model_number', 'qa_number', 'country_of_manufacture', 'network_connectivity'] as $field) {
                $table->string($field)->nullable();
            }
        });
        DB::table('products')->insert([
            ['id' => 1, 'business_id' => 1, 'brand_id' => 1, 'category_id' => 10, 'name' => 'Phone', 'model_number' => 'DEFAULT', 'qa_number' => 'QA-DEFAULT', 'country_of_manufacture' => 'China', 'network_connectivity' => 'SIM & E-SIM'],
            ['id' => 2, 'business_id' => 2, 'brand_id' => 2, 'category_id' => 20, 'name' => 'Private phone', 'model_number' => 'PRIVATE', 'qa_number' => 'PRIVATE', 'country_of_manufacture' => 'China', 'network_connectivity' => 'SIM'],
        ]);
        DB::table('brands')->insert([['id' => 1, 'business_id' => 1, 'name' => 'Apple'], ['id' => 2, 'business_id' => 2, 'name' => 'Private brand']]);
        DB::table('contacts')->insert(['id' => 1, 'business_id' => 1, 'name' => 'Supplier Person', 'supplier_business_name' => 'Supplier Company']);
        foreach ([1, 2, 3, 4, 5, 6, 7] as $id) {
            DB::table('transactions')->insert(['id' => $id, 'business_id' => $id === 3 ? 2 : 1,
                'location_id' => $id === 2 ? 2 : 1, 'contact_id' => 1,
                'type' => $id === 5 ? 'sell' : 'purchase', 'status' => $id === 4 ? 'ordered' : 'received',
                'transaction_date' => $id === 2 ? '2026-11-02 09:00:00' : '2026-10-26 23:59:59', 'additional_notes' => 'Receipt note']);
            DB::table('purchase_lines')->insert(['id' => $id, 'transaction_id' => $id,
                'product_id' => in_array($id, [3, 7]) ? 2 : 1, 'quantity' => 3,
                'quantity_returned' => $id === 6 ? 3 : 1, 'purchase_price_inc_tax' => 100,
                'model_number' => $id === 1 ? 'LOT-MODEL' : null, 'qa_number' => $id === 1 ? '' : null]);
        }
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('telecom_test');
        parent::tearDown();
    }

    public function test_received_purchases_use_saved_details_and_correct_net_totals()
    {
        $rows = (new TelecomSsdReport)->query(1, 'all', [])->orderBy('pl.id')->get();
        $this->assertSame([1, 2], $rows->pluck('id')->all());
        $this->assertSame('LOT-MODEL', $rows[0]->model_number);
        $this->assertSame('', $rows[0]->qa_number);
        $this->assertSame('DEFAULT', $rows[1]->model_number);
        $this->assertSame('QA-DEFAULT', $rows[1]->qa_number);
        $this->assertSame('SIM & E-SIM', $rows[0]->sim);
        $this->assertSame('Apple', $rows[0]->trademark);
        $this->assertSame('Supplier Company', $rows[0]->client_name);
        $this->assertEquals(2, $rows[0]->quantity);
        $this->assertEquals(100, $rows[0]->unit_price);
        $this->assertEquals(200, $rows[0]->total_price);
        $this->assertSame('Receipt note', $rows[0]->remark);
    }

    public function test_report_works_before_purchase_detail_migration_using_product_defaults()
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['model_number', 'qa_number', 'country_of_manufacture', 'network_connectivity']);
        });
        $report = new TelecomSsdReport;
        $row = $report->query(1, [1], [])->first();
        $this->assertSame('DEFAULT', $row->model_number);
        $this->assertSame('QA-DEFAULT', $row->qa_number);
        $this->assertSame('China', $row->country_of_manufacture);
        $this->assertSame('SIM & E-SIM', $row->sim);
        $this->assertSame(1, $report->query(1, [1], ['search_text' => 'QA-DEFAULT'])->count());
        $this->assertCount(13, $report->csvRow($row, 1));
    }

    public function test_report_works_before_both_detail_migrations_and_keeps_purchase_totals()
    {
        foreach (['purchase_lines', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['model_number', 'qa_number', 'country_of_manufacture', 'network_connectivity']);
            });
        }
        $report = new TelecomSsdReport;
        $rows = $report->query(1, [1], [])->get();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->model_number);
        $this->assertNull($rows[0]->qa_number);
        $this->assertEquals(200, $rows[0]->total_price);
        $this->assertSame(1, $report->query(1, [1], ['search_text' => 'Phone'])->count());
        $this->assertCount(13, $report->csvRow($rows[0], 1));
    }

    public function test_business_location_date_supplier_and_search_filters_are_enforced()
    {
        $report = new TelecomSsdReport;
        $this->assertSame([1], $report->query(1, [1], [])->pluck('pl.id')->all());
        $this->assertSame(0, $report->query(1, [1], ['location_id' => 2])->count());
        $this->assertSame(0, $report->query(1, [], [])->count());
        $this->assertSame([3], $report->query(2, 'all', [])->pluck('pl.id')->all());
        $this->assertSame([1], $report->query(1, 'all', ['start_date' => '2026-10-26', 'end_date' => '2026-10-26'])->pluck('pl.id')->all());
        $this->assertSame(0, $report->query(1, 'all', ['supplier_id' => 99])->count());
        $this->assertSame(0, $report->query(1, 'all', ['category_id' => 20])->count());
        $this->assertSame(0, $report->query(1, 'all', ['brand_id' => 2])->count());
        $this->assertSame([1], $report->query(1, 'all', ['search_text' => 'LOT-MODEL'])->pluck('pl.id')->all());
    }

    private function allowReport(): void
    {
        Auth::shouldReceive('user')->andReturn(new class {
            public function can($permission) { return $permission === 'purchase_n_sell_report.view'; }
            public function permitted_locations() { return [1]; }
        });
    }

    private function reportRequest(array $data): Request
    {
        $request = Request::create('/reports/telecom-ssd-report', 'GET', $data);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('user.business_id', 1);
        return $request;
    }

    public function test_csv_exports_all_filtered_rows_with_the_exact_headers()
    {
        $this->allowReport();
        $response = (new TelecomSsdReportController)->index($this->reportRequest(['export' => 'csv']), new TelecomSsdReport);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $lines = explode("\n", trim(substr($csv, 3)));
        $this->assertSame(TelecomSsdReport::HEADINGS, str_getcsv($lines[0], ',', '"', ''));
        $this->assertCount(2, $lines);
        $row = str_getcsv($lines[1], ',', '"', '');
        $this->assertCount(13, $row);
        $this->assertSame('LOT-MODEL', $row[2]);
        $this->assertSame('Supplier Company', $row[11]);
        $this->assertSame('2026-10-26', $row[10]);
    }

    public function test_ajax_paginates_authorized_rows_and_escapes_product_details()
    {
        $this->allowReport();
        DB::table('transactions')->where('id', 2)->update(['location_id' => 1]);
        DB::table('purchase_lines')->where('id', 1)->update(['model_number' => '<script>alert(1)</script>']);
        $request = $this->reportRequest(['draw' => 1, 'start' => 1, 'length' => 1]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->app->instance('request', $request);
        $response = (new TelecomSsdReportController)->index($request, new TelecomSsdReport);
        $data = $response->getData(true);
        $this->assertSame(2, $data['recordsTotal']);
        $this->assertSame(2, $data['recordsFiltered']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(2, $data['data'][0][config('datatables.index_column')]);
        $this->assertStringContainsString('&lt;script&gt;', $data['data'][0]['model_number']);
        $this->assertStringNotContainsString('<script>', $response->getContent());
    }

    public function test_ajax_sorts_totals_numerically_before_pagination()
    {
        $this->allowReport();
        DB::table('transactions')->where('id', 2)->update(['location_id' => 1]);
        DB::table('purchase_lines')->where('id', 2)->update(['quantity' => 11, 'purchase_price_inc_tax' => 2]);
        $request = $this->reportRequest(['draw' => 1, 'start' => 0, 'length' => 1,
            'order' => [['column' => 9, 'dir' => 'asc']]]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->app->instance('request', $request);
        $data = (new TelecomSsdReportController)->index($request, new TelecomSsdReport)->getData(true);
        $this->assertSame(2, $data['recordsFiltered']);
        $this->assertSame(2, $data['data'][0]['id']);
        $this->assertEquals(20, $data['data'][0]['total_price']);
        $this->assertSame(1, $data['data'][0][config('datatables.index_column')]);
    }

    public function test_all_report_columns_sort_using_selected_aliases_with_legacy_schema()
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['model_number', 'qa_number', 'country_of_manufacture', 'network_connectivity']);
        });
        $report = new TelecomSsdReport;
        foreach (range(1, 12) as $column) {
            $query = $report->query(1, 'all', []);
            $this->assertCount(2, $report->applyOrder($query, [['column' => $column, 'dir' => 'asc']])->get());
        }
        $query = $report->query(1, 'all', []);
        $rows = $report->applyOrder($query, [['column' => 10, 'dir' => 'asc']])->get();
        $this->assertSame([1, 2], $rows->pluck('id')->all());
    }

    public function test_csv_export_preserves_the_selected_sort_order()
    {
        $this->allowReport();
        DB::table('transactions')->where('id', 2)->update(['location_id' => 1]);
        $request = $this->reportRequest(['export' => 'csv', 'order' => [['column' => 2, 'dir' => 'desc']]]);
        $response = (new TelecomSsdReportController)->index($request, new TelecomSsdReport);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $lines = explode("\n", trim(substr($csv, 3)));
        $this->assertCount(3, $lines);
        $this->assertSame('LOT-MODEL', str_getcsv($lines[1], ',', '"', '')[2]);
        $this->assertSame('DEFAULT', str_getcsv($lines[2], ',', '"', '')[2]);
    }

    public function test_end_date_can_be_used_without_a_start_date()
    {
        $this->allowReport();
        $response = (new TelecomSsdReportController)->index($this->reportRequest(['end_date' => '2026-10-26', 'export' => 'csv']), new TelecomSsdReport);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_xlsx_export_contains_all_filtered_rows_with_text_identifiers_and_numeric_prices()
    {
        $this->allowReport();
        config(['excel.temporary_files.local_path' => sys_get_temp_dir() . '/telecom-ssd-excel-tests']);
        DB::table('transactions')->where('id', 2)->update(['location_id' => 1]);
        DB::table('purchase_lines')->where('id', 1)->update(['qa_number' => '00123', 'model_number' => '=1+1']);
        $request = $this->reportRequest(['export' => 'xlsx', 'length' => 1]);
        $response = (new TelecomSsdReportController)->index($request, new TelecomSsdReport);
        $path = $response->getFile()->getPathname();
        try {
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame(4, $sheet->getHighestRow());
            $this->assertSame(TelecomSsdReport::KHMER_HEADINGS, $sheet->rangeToArray('A1:M1')[0]);
            $this->assertSame(TelecomSsdReport::HEADINGS, $sheet->rangeToArray('A2:M2')[0]);
            $this->assertSame('dotted', $sheet->getStyle('B3')->getBorders()->getBottom()->getBorderStyle());
            $this->assertSame('thin', $sheet->getStyle('B1')->getBorders()->getRight()->getBorderStyle());
            $this->assertTrue($sheet->getStyle('A2')->getFont()->getBold());
            $this->assertSame('Khmer OS Battambang', $sheet->getStyle('A1')->getFont()->getName());
            $this->assertSame('landscape', $sheet->getPageSetup()->getOrientation());
            $this->assertSame('A3', $sheet->getFreezePane());
            $this->assertSame('00123', $sheet->getCell('D4')->getValue());
            $this->assertSame('s', $sheet->getCell('D4')->getDataType());
            $this->assertSame('=1+1', $sheet->getCell('C4')->getValue());
            $this->assertSame('s', $sheet->getCell('C4')->getDataType());
            $this->assertEquals(200, $sheet->getCell('J4')->getValue());
            $this->assertSame('n', $sheet->getCell('J4')->getDataType());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_print_export_contains_all_filtered_rows_in_sorted_order_and_escapes_text()
    {
        $this->allowReport();
        DB::table('transactions')->where('id', 2)->update(['location_id' => 1]);
        DB::table('transactions')->where('id', 1)->update(['additional_notes' => '<script>unsafe()</script>']);
        $request = $this->reportRequest(['export' => 'print', 'length' => 1, 'order' => [['column' => 2, 'dir' => 'desc']]]);
        $view = (new TelecomSsdReportController)->index($request, new TelecomSsdReport);
        $this->assertSame('report.telecom_ssd_print', $view->name());
        $data = $view->getData();
        $rows = iterator_to_array($data['rows']);
        $this->assertCount(2, $rows);
        $this->assertSame('LOT-MODEL', $rows[0]->model_number);
        // Render the standalone print view without unrelated module view composers.
        request()->headers->set('X-Requested-With', 'XMLHttpRequest');
        Auth::shouldReceive('check')->andReturn(false);
        $data['rows'] = $rows;
        $html = view('report.telecom_ssd_print', $data)->render();
        foreach (array_merge(TelecomSsdReport::KHMER_HEADINGS, TelecomSsdReport::HEADINGS) as $heading) {
            $this->assertStringContainsString(e($heading), $html);
        }
        $this->assertStringContainsString('&lt;script&gt;unsafe()', $html);
        $this->assertStringNotContainsString('<script>unsafe()', $html);
    }

    public function test_report_denies_users_without_report_permission()
    {
        Auth::shouldReceive('user')->andReturn(new class { public function can($permission) { return false; } });
        $this->expectException(HttpException::class);
        (new TelecomSsdReportController)->index($this->reportRequest([]), new TelecomSsdReport);
    }

    public function test_csv_escapes_formulas_and_report_view_compiles()
    {
        $report = new TelecomSsdReport;
        $row = $report->query(1, 'all', [])->first();
        $row->remark = '=HYPERLINK("example")';
        $this->assertStringStartsWith("'=", $report->csvRow($row, 1)[12]);
        $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('views/report/telecom_ssd_report.blade.php')));
        $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
        $this->assertSame('/reports/telecom-ssd-report', parse_url(route('reports.telecom-ssd'), PHP_URL_PATH));
    }
}
