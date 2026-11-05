<?php

namespace Tests\Feature;

use App\Events\ExpenseCreatedOrModified;
use App\Transaction;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\LocalCashierReport\Http\Controllers\FinancialDashboardController;
use Modules\LocalCashierReport\Http\Controllers\FinancialExpenseController;
use Modules\LocalCashierReport\Services\FinancialDashboardReport;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinancialExpenseModalTest extends TestCase
{
    private $originalConnection;
    private $permissions = ['profit_loss_report.view', 'all_expense.access', 'expense.edit'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config(['database.default' => 'expense_modal_main', 'accessory.database_connection' => 'expense_modal_accessory', 'service.database_connection' => 'expense_modal_service']);
        foreach (['main', 'accessory', 'service'] as $source) {
            $connection = 'expense_modal_'.$source;
            config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            $schema = Schema::connection($connection);
            $schema->create('transactions', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('business_id')->default(1);
                $table->integer('location_id')->default(1);
                $table->integer('created_by')->default(7);
                $table->integer('expense_for')->nullable();
                $table->string('type')->default('expense');
                $table->string('status')->default('final');
                $table->string('transaction_date')->default('2026-11-03 12:00:00');
                $table->string('ref_no')->nullable();
                $table->text('additional_notes')->nullable();
                $table->decimal('final_total');
                $table->decimal('total_before_tax')->default(0);
                $table->decimal('tax_amount')->default(0);
                $table->integer('tax_id')->nullable()->default(1);
                $table->string('payment_status')->default('partial');
                $table->integer('expense_category_id')->nullable()->default(1);
                $table->integer('expense_sub_category_id')->nullable()->default(2);
                $table->boolean('is_recurring')->default(true);
                $table->integer('recur_interval')->default(2);
                $table->string('recur_interval_type')->default('months');
                $table->integer('recur_repetitions')->default(5);
                $table->integer('subscription_repeat_on')->default(4);
                $table->timestamps();
            });
            $schema->create('expense_categories', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('business_id')->default(1);
                $table->string('name');
                $table->integer('parent_id')->nullable();
                $table->softDeletes();
            });
            $schema->create('business_locations', function (Blueprint $table) {
                $table->integer('id');
                $table->string('name');
            });
            $schema->create('tax_rates', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('business_id');
                $table->decimal('amount');
                $table->softDeletes();
            });
            $schema->create('transaction_payments', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('transaction_id');
                $table->decimal('amount');
                $table->boolean('is_return')->default(false);
            });
            $schema->create('system', function (Blueprint $table) {
                $table->string('key'); $table->string('value');
            });
            $schema->create('activity_log', function (Blueprint $table) {
                $table->increments('id');
                $table->string('log_name'); $table->string('description');
                $table->integer('subject_id'); $table->string('subject_type');
                $table->integer('business_id'); $table->text('properties'); $table->timestamps();
            });
            $db = DB::connection($connection);
            $db->getPdo()->sqliteCreateFunction('IF', fn ($condition, $yes, $no) => $condition ? $yes : $no);
            $db->table('transactions')->insert(['id' => 1, 'final_total' => $source === 'main' ? 100 : 200, 'updated_at' => '2026-11-01 12:00:00']);
            $db->table('transaction_payments')->insert(['id' => 1, 'transaction_id' => 1, 'amount' => 40]);
            $db->table('business_locations')->insert(['id' => 1, 'name' => 'Branch A']);
            $db->table('tax_rates')->insert(['id' => 1, 'business_id' => 1, 'amount' => 10]);
            $db->table('expense_categories')->insert([
                ['id' => 1, 'business_id' => 1, 'name' => 'Office', 'parent_id' => null],
                ['id' => 2, 'business_id' => 1, 'name' => 'Supplies', 'parent_id' => 1],
                ['id' => 9, 'business_id' => 2, 'name' => 'Private', 'parent_id' => null],
            ]);
        }
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->shouldReceive('can')->andReturnUsing(fn ($permission) => in_array($permission, $this->permissions));
        $this->actingAs($user);
        session()->put('user.business_id', 1);
        Event::fake([ExpenseCreatedOrModified::class]);
    }

    protected function tearDown(): void
    {
        foreach (['main', 'accessory', 'service'] as $source) DB::purge('expense_modal_'.$source);
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function request(array $data = [])
    {
        $request = Request::create('/', 'PUT', $data);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);

        return $request;
    }

    private function controller()
    {
        $dashboard = Mockery::mock(FinancialDashboardController::class);
        $dashboard->shouldReceive('context')->andReturnUsing(function ($request) {
            abort_unless($request->user()->can('profit_loss_report.view'), 403);

            return [1, collect([(object) ['id' => 1], (object) ['id' => -2], (object) ['id' => -3]]), [
                'source' => $request->input('source', 'all'), 'location_ids' => $request->input('location_ids', [1, -2, -3]),
                'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
                'expense_filter_applied' => $request->boolean('expense_filter_applied'),
                'expense_categories' => $request->input('expense_categories', []),
                'excluded_expense_categories' => $request->input('excluded_expense_categories', []),
            ]];
        });
        $util = Mockery::mock(TransactionUtil::class)->makePartial();
        $util->shouldReceive('activityLog')->andReturnNull();
        $modules = Mockery::mock(ModuleUtil::class);
        $modules->shouldReceive('isSubscribed')->andReturn(true);

        return new FinancialExpenseController($dashboard, new FinancialDashboardReport, $util, $modules);
    }

    private function editData(): array
    {
        return ['ref_no' => 'EXP-UPDATED', 'transaction_date' => '2026-11-04T09:30:00', 'final_total' => 110,
            'additional_notes' => 'Corrected note', 'expense_category_id' => 1, 'expense_sub_category_id' => 2, 'updated_at' => '2026-11-01 12:00:00'];
    }

    public function test_expense_pagination_and_sorting_merge_all_sources_before_selecting_a_page()
    {
        $expected = [100, 200, 200];
        foreach (['main', 'accessory', 'service'] as $offset => $source) {
            for ($id = 2; $id <= 13; $id++) {
                $amount = $id * 3 + $offset;
                DB::connection('expense_modal_'.$source)->table('transactions')->insert(['id' => $id, 'final_total' => $amount, 'ref_no' => sprintf('EXP-%02d', 14 - $id)]);
                $expected[] = $amount;
            }
        }
        DB::table('transactions')->insert(['id' => 14, 'type' => 'expense_refund', 'final_total' => 5]);
        $expected[] = -5;
        sort($expected, SORT_NUMERIC);
        $all = $this->controller()->index($this->request(['per_page' => -1, 'sort' => 'amount', 'direction' => 'asc']))->getData(true);
        $this->assertEquals($expected, array_column($all['rows'], 'amount'));
        $this->assertEquals(1, $all['last_page']);
        $page = $this->controller()->index($this->request(['per_page' => 10, 'page' => 2, 'sort' => 'amount', 'direction' => 'asc']))->getData(true);
        $this->assertEquals(array_slice($expected, 10, 10), array_column($page['rows'], 'amount'));
        $this->assertEquals(4, $page['last_page']);
        $descending = $this->controller()->index($this->request(['per_page' => 10, 'sort' => 'amount', 'direction' => 'desc']))->getData(true);
        $this->assertEquals(array_slice(array_reverse($expected), 0, 10), array_column($descending['rows'], 'amount'));
        $refs = $this->controller()->index($this->request(['per_page' => 10, 'sort' => 'reference', 'direction' => 'asc', 'source' => 'main']))->getData(true);
        $referenceValues = array_column($refs['rows'], 'ref_no');
        $sortedReferences = $referenceValues;
        sort($sortedReferences, SORT_STRING);
        $this->assertEquals($sortedReferences, $referenceValues);
        $this->expectException(ValidationException::class);
        $this->controller()->index($this->request(['sort' => 't.created_by']));
    }

    public function test_list_matches_category_location_and_source_filters_with_signed_refunds()
    {
        DB::table('transactions')->insert(['id' => 2, 'type' => 'expense_refund', 'final_total' => 5]);
        $result = $this->controller()->index($this->request(['source' => 'main', 'expense_filter_applied' => 1, 'expense_categories' => ['Office']]))->getData(true);
        $this->assertSame(2, $result['total']);
        $this->assertEquals(95, $result['amount']);
        $this->assertSame('main', $result['rows'][0]['source']);
        $this->assertEquals(-5, $result['rows'][0]['amount']);
        $empty = $this->controller()->index($this->request(['excluded_expense_categories' => ['Office']]))->getData(true);
        $this->assertSame(0, $empty['total']);
    }

    public function test_modal_update_reuses_tax_and_payment_logic_and_preserves_recurrence()
    {
        $result = $this->controller()->update($this->request($this->editData()), 'main', 1)->getData(true);
        $this->assertTrue($result['success']);
        $record = DB::table('transactions')->where('id', 1)->first();
        $this->assertEquals(110, $record->final_total);
        $this->assertEquals(100, $record->total_before_tax);
        $this->assertEquals(10, $record->tax_amount);
        $this->assertSame('partial', $record->payment_status);
        $this->assertEquals(1, $record->is_recurring);
        $this->assertEquals(2, $record->recur_interval);
        $this->assertEquals(5, $record->recur_repetitions);
        $this->assertEquals(4, $record->subscription_repeat_on);
        $this->assertSame('Corrected note', $record->additional_notes);
        $this->assertEquals(40, DB::table('transaction_payments')->sum('amount'));
        Event::assertDispatched(ExpenseCreatedOrModified::class);
    }

    public function test_accessory_update_isolated_from_same_transaction_id_in_main_source()
    {
        $this->controller()->update($this->request($this->editData()), 'accessory', 1);
        $this->assertEquals(100, DB::table('transactions')->where('id', 1)->value('final_total'));
        $this->assertEquals(110, DB::connection('expense_modal_accessory')->table('transactions')->where('id', 1)->value('final_total'));
        $this->assertSame('expense_modal_main', config('database.default'));
        $this->assertSame(1, DB::connection('expense_modal_accessory')->table('activity_log')->count());
    }

    public function test_viewer_cannot_edit_and_list_does_not_offer_edit_links()
    {
        $this->permissions = ['profit_loss_report.view', 'all_expense.access'];
        $result = $this->controller()->index($this->request(['source' => 'main']))->getData(true);
        $this->assertNull($result['rows'][0]['edit_url']);
        $this->expectException(HttpException::class);
        $this->controller()->update($this->request($this->editData()), 'main', 1);
    }

    public function test_own_only_permission_does_not_reuse_user_ids_in_other_sources()
    {
        $this->permissions = ['profit_loss_report.view', 'view_own_expense', 'expense.edit'];
        $this->expectException(HttpException::class);
        $this->controller()->edit($this->request(), 'accessory', 1);
    }

    public function test_other_business_or_location_cannot_be_updated()
    {
        DB::table('transactions')->where('id', 1)->update(['location_id' => 999]);
        $this->expectException(ModelNotFoundException::class);
        $this->controller()->update($this->request($this->editData()), 'main', 1);
    }

    public function test_stale_form_cannot_overwrite_a_newer_expense()
    {
        DB::table('transactions')->where('id', 1)->update(['updated_at' => '2026-11-02 12:00:00']);
        $this->expectException(HttpException::class);
        $this->controller()->update($this->request($this->editData()), 'main', 1);
    }

    public function test_edit_returns_a_canonical_version_and_preserves_archived_category_options()
    {
        DB::table('expense_categories')->where('id', 1)->update(['deleted_at' => '2026-11-02']);
        $data = $this->controller()->edit($this->request(), 'main', 1)->getData(true);
        $this->assertSame('2026-11-01 12:00:00', $data['expense']['updated_at']);
        $this->assertContains(1, array_column($data['categories'], 'id'));
        $this->controller()->update($this->request($this->editData()), 'main', 1);
        $this->assertEquals(1, DB::table('transactions')->value('expense_category_id'));
    }

    public function test_foreign_category_is_rejected()
    {
        $data = $this->editData();
        $data['expense_category_id'] = 9;
        $data['expense_sub_category_id'] = null;
        $this->expectException(ValidationException::class);
        $this->controller()->update($this->request($data), 'main', 1);
    }
}
