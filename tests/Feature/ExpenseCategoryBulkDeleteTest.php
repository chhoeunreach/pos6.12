<?php

namespace Tests\Feature;

use App\ExpenseCategory;
use App\Http\Controllers\ExpenseCategoryController;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExpenseCategoryBulkDeleteTest extends TestCase
{
    private $originalConnection;
    private $allowed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'expense_bulk_test',
            'database.connections.expense_bulk_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->integer('parent_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('expense_category_id');
            $table->decimal('final_total');
        });
        ExpenseCategory::insert([
            ['id' => 1, 'business_id' => 1, 'name' => 'Match root', 'parent_id' => null],
            ['id' => 2, 'business_id' => 1, 'name' => 'Match child', 'parent_id' => 1],
            ['id' => 3, 'business_id' => 1, 'name' => 'Other', 'parent_id' => null],
            ['id' => 4, 'business_id' => 2, 'name' => 'Match other business', 'parent_id' => null],
        ]);
        DB::table('transactions')->insert(['expense_category_id' => 1, 'final_total' => 100]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('can')->andReturnUsing(fn () => $this->allowed);
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        DB::purge('expense_bulk_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function request(array $data, $method = 'POST')
    {
        $request = Request::create('/expense-categories', $method, $data);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('user.business_id', 1);
        $this->app->instance('request', $request);

        return $request;
    }

    public function test_deleting_selected_parent_and_child_counts_each_once_and_preserves_expenses()
    {
        $result = (new ExpenseCategoryController)->bulkDestroy($this->request(['ids' => [1, 2]]));
        $this->assertTrue($result['success']);
        $this->assertSame('2 categories deleted successfully.', $result['msg']);
        $this->assertSame([3, 4], ExpenseCategory::orderBy('id')->pluck('id')->all());
        $this->assertSame(2, ExpenseCategory::onlyTrashed()->count());
        $this->assertSame(1, DB::table('transactions')->count());
        $this->assertEquals(100, DB::table('transactions')->value('final_total'));
    }

    public function test_deleting_only_a_child_keeps_its_parent()
    {
        $this->assertTrue((new ExpenseCategoryController)->bulkDestroy($this->request(['ids' => [2]]))['success']);
        $this->assertSame([1, 3, 4], ExpenseCategory::orderBy('id')->pluck('id')->all());
    }

    public function test_cross_business_selection_is_rejected_without_partial_deletion()
    {
        $result = (new ExpenseCategoryController)->bulkDestroy($this->request(['ids' => [1, 4]]));
        $this->assertFalse($result['success']);
        $this->assertSame(4, ExpenseCategory::count());
    }

    public function test_stale_selection_is_rejected_without_partial_deletion()
    {
        $result = (new ExpenseCategoryController)->bulkDestroy($this->request(['ids' => [1, 999]]));
        $this->assertFalse($result['success']);
        $this->assertSame(4, ExpenseCategory::count());
    }

    public function test_requires_category_permission()
    {
        $this->allowed = false;
        $this->expectException(HttpException::class);
        (new ExpenseCategoryController)->bulkDestroy($this->request(['ids' => [1]]));
    }

    public function test_empty_selection_is_invalid()
    {
        $this->expectException(ValidationException::class);
        (new ExpenseCategoryController)->bulkDestroy($this->request(['ids' => []]));
    }

    public function test_select_all_table_request_returns_named_ids_for_all_matching_rows_in_this_business()
    {
        $this->request([
            'draw' => 1, 'start' => 0, 'length' => -1,
            'search' => ['value' => 'Match', 'regex' => 'false'],
            'columns' => [
                ['data' => '', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'code', 'name' => 'code', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
            ],
        ], 'GET');
        $response = (new ExpenseCategoryController)->index()->getData(true);
        $this->assertSame(3, $response['recordsTotal']);
        $this->assertSame(2, $response['recordsFiltered']);
        $this->assertEqualsCanonicalizing([1, 2], array_column($response['data'], 'id'));
        $this->assertArrayHasKey('action', $response['data'][0]);
    }
}
