<?php

namespace Tests\Feature;

use App\ExpenseCategory;
use App\Http\Controllers\ExpenseCategoryController;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExpenseCategoryImportTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'expense_import_test',
            'database.connections.expense_import_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
            'excel.temporary_files.local_path' => sys_get_temp_dir().'/expense-category-import-tests',
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
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('can')->andReturn(true);
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        DB::purge('expense_import_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function request(array $data = [], array $files = [])
    {
        $request = Request::create('/expense-categories/import', 'POST', $data, [], $files);
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('user.business_id', 1);
        $request->session()->put('user.id', 1);

        return $request;
    }

    public function previewCsv(string $csv, string $existingAction = 'keep')
    {
        return (new ExpenseCategoryController)->importPreview($this->request(['existing_action' => $existingAction], [
            'file' => UploadedFile::fake()->createWithContent('categories.csv', $csv),
        ]));
    }

    private function confirm(array $preview)
    {
        return (new ExpenseCategoryController)->importConfirm($this->request(['token' => $preview['token']]));
    }

    public function test_first_parent_is_kept_and_khmer_children_import_under_it()
    {
        $parent = 'Administrative & Office Expenses / ចំណាយរដ្ឋបាល និងការិយាល័យ';
        $child = 'Office Supplies / សម្ភារៈការិយាល័យ';
        $csv = "\xEF\xBB\xBFname,code,parent_category\n$parent,100,\n$child,100001,$parent\n";
        $preview = $this->previewCsv($csv);

        $this->assertTrue($preview['success']);
        $this->assertSame(2, $preview['summary']['total_rows']);
        $this->assertSame(2, $preview['summary']['new_rows']);
        $this->assertSame(0, $preview['summary']['error_rows']);
        $this->assertSame([2, 3], array_column($preview['rows'], 'row_number'));
        $this->assertSame($parent, $preview['rows'][0]['name']);
        $this->assertSame(0, ExpenseCategory::count());

        $this->assertTrue($this->confirm($preview)['success']);
        $root = ExpenseCategory::where('name', $parent)->firstOrFail();
        $sub = ExpenseCategory::where('name', $child)->firstOrFail();
        $this->assertNull($root->parent_id);
        $this->assertEquals($root->id, $sub->parent_id);
        $this->assertSame('100001', $sub->code);

        $again = $this->previewCsv($csv);
        $this->assertSame(2, $again['summary']['existing_rows']);
        $this->assertTrue($this->confirm($again)['success']);
        $this->assertSame(2, ExpenseCategory::count());
    }

    public function test_children_can_appear_before_their_new_parents()
    {
        $preview = $this->previewCsv("name,code,parent_category\nChild,101,Parent\nParent,100,\n");
        $this->assertSame(0, $preview['summary']['error_rows']);
        $this->assertTrue($this->confirm($preview)['success']);
        $this->assertEquals(
            ExpenseCategory::where('name', 'Parent')->value('id'),
            ExpenseCategory::where('name', 'Child')->value('parent_id')
        );
    }

    public function test_missing_or_nested_parents_are_rejected_without_importing()
    {
        $preview = $this->previewCsv("name,code,parent_category\nRoot,100,\nChild,101,Root\nNested,102,Child\nOrphan,103,Missing\nSelf,104,Self\n");
        $this->assertSame(3, $preview['summary']['error_rows']);
        $this->assertFalse($this->confirm($preview)['success']);
        $this->assertSame(0, ExpenseCategory::count());
    }

    public function test_parent_from_another_business_is_not_accepted()
    {
        ExpenseCategory::create(['business_id' => 2, 'name' => 'Parent']);
        $preview = $this->previewCsv("name,code,parent_category\nChild,101,Parent\n");
        $this->assertSame(1, $preview['summary']['error_rows']);
        $this->assertFalse($this->confirm($preview)['success']);
    }

    public function test_deleted_parent_after_preview_rolls_back_the_whole_import()
    {
        $parent = ExpenseCategory::create(['business_id' => 1, 'name' => 'Existing']);
        $preview = $this->previewCsv("name,code,parent_category\nNewRoot,100,\nChild,101,Existing\n");
        $this->assertSame(0, $preview['summary']['error_rows']);
        $parent->delete();
        $this->assertFalse($this->confirm($preview)['success']);
        $this->assertSame(0, ExpenseCategory::count());
    }

    public function test_keep_preserves_existing_code_and_parent_and_imports_new_categories()
    {
        $root = ExpenseCategory::create(['business_id' => 1, 'name' => 'Root', 'code' => 'original']);
        $child = ExpenseCategory::create(['business_id' => 1, 'name' => 'Child', 'code' => 'child-code', 'parent_id' => $root->id]);
        $before = $child->fresh()->getAttributes();
        $preview = $this->previewCsv("name,code,parent_category\nRoot,changed,Missing\nChild,changed,Missing\nNew,102,Root\n", 'keep');
        $this->assertSame(0, $preview['summary']['error_rows']);
        $this->assertSame(2, $preview['summary']['existing_rows']);
        $this->assertSame(2, $preview['summary']['skipped_rows']);
        $result = $this->confirm($preview);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Inserted: 1, Updated: 0, Skipped: 2', $result['msg']);
        $this->assertSame('original', $root->fresh()->code);
        $this->assertSame($before, $child->fresh()->getAttributes());
        $this->assertEquals($root->id, ExpenseCategory::where('name', 'New')->value('parent_id'));
    }

    public function test_update_replaces_existing_code_and_parent_and_imports_new_categories()
    {
        $root = ExpenseCategory::create(['business_id' => 1, 'name' => 'Root', 'code' => 'original']);
        $child = ExpenseCategory::create(['business_id' => 1, 'name' => 'Child', 'code' => 'old', 'parent_id' => $root->id]);
        $preview = $this->previewCsv("name,code,parent_category\nRoot,updated,\nChild,new-code,NewRoot\nNewRoot,102,\n", 'update');
        $this->assertSame(0, $preview['summary']['error_rows']);
        $this->assertSame(0, $preview['summary']['skipped_rows']);
        $result = $this->confirm($preview);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Inserted: 1, Updated: 2, Skipped: 0', $result['msg']);
        $this->assertSame('updated', $root->fresh()->code);
        $this->assertSame('new-code', $child->fresh()->code);
        $this->assertEquals(ExpenseCategory::where('name', 'NewRoot')->value('id'), $child->fresh()->parent_id);
        $this->assertSame($child->id, $child->fresh()->id);
    }

    public function test_keep_validates_new_children_against_the_preserved_parent_hierarchy()
    {
        $root = ExpenseCategory::create(['business_id' => 1, 'name' => 'Root']);
        ExpenseCategory::create(['business_id' => 1, 'name' => 'Child', 'parent_id' => $root->id]);
        $csv = "name,code,parent_category\nChild,101,\nNew,102,Child\n";
        $keep = $this->previewCsv($csv, 'keep');
        $this->assertSame(1, $keep['summary']['error_rows']);
        $this->assertFalse($this->confirm($keep)['success']);
        $update = $this->previewCsv($csv, 'update');
        $this->assertSame(0, $update['summary']['error_rows']);
        $this->assertTrue($this->confirm($update)['success']);
        $this->assertNull(ExpenseCategory::where('name', 'Child')->value('parent_id'));
    }

    public function test_khmer_workbook_headings_are_supported_and_notes_sheets_are_ignored()
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray([
            ['ឈ្មោះប្រភេទ', 'លេខកូដ', 'ប្រភេទមេ'],
            ['ចំណាយរដ្ឋបាល និងការិយាល័យ', '100', ''],
            ['សម្ភារៈការិយាល័យ', '100001', 'ចំណាយរដ្ឋបាល និងការិយាល័យ'],
        ]);
        $workbook->createSheet()->fromArray([['ចំណុច', 'ព័ត៌មាន'], ['ចំនួនសរុប', 2]]);
        $path = tempnam(sys_get_temp_dir(), 'expense-workbook-');
        try {
            (new Xlsx($workbook))->save($path);
            $file = UploadedFile::fake()->createWithContent('categories.xlsx', file_get_contents($path));
            $preview = (new ExpenseCategoryController)->importPreview($this->request([], ['file' => $file]));
        } finally {
            unlink($path);
            $workbook->disconnectWorksheets();
        }
        $this->assertTrue($preview['success']);
        $this->assertSame(2, $preview['summary']['total_rows']);
        $this->assertSame(0, $preview['summary']['error_rows']);
        $this->assertSame('សម្ភារៈការិយាល័យ', $preview['rows'][1]['name']);
        $this->assertTrue($this->confirm($preview)['success']);
        $this->assertSame('100001', ExpenseCategory::whereNotNull('parent_id')->value('code'));
    }

    public function test_english_display_headings_are_also_supported()
    {
        $preview = $this->previewCsv("Category name,Category code,Parent Category\nRoot,100,\nChild,101,Root\n");
        $this->assertTrue($preview['success']);
        $this->assertSame(2, $preview['summary']['total_rows']);
        $this->assertSame(0, $preview['summary']['error_rows']);
    }

    public function test_unknown_headings_and_empty_files_show_clear_errors()
    {
        $unknown = $this->previewCsv("Unsupported,code,parent_category\nRoot,100,\n");
        $this->assertFalse($unknown['success']);
        $this->assertStringContainsString('Category name column not found', $unknown['msg']);
        $empty = $this->previewCsv("name,code,parent_category\n");
        $this->assertFalse($empty['success']);
        $this->assertStringContainsString('No categories found', $empty['msg']);
    }

    public function test_nonempty_rows_without_a_name_are_reported_instead_of_disappearing()
    {
        $preview = $this->previewCsv("name,code,parent_category\n,100,\n");
        $this->assertTrue($preview['success']);
        $this->assertSame(1, $preview['summary']['total_rows']);
        $this->assertSame(1, $preview['summary']['error_rows']);
        $this->assertSame(['name is required'], $preview['rows'][0]['errors']);
        $this->assertFalse($this->confirm($preview)['success']);
    }

    public function test_create_rejects_a_duplicate_code_with_a_clear_message()
    {
        ExpenseCategory::create(['business_id' => 1, 'name' => 'Original', 'code' => 'ABC']);
        $result = (new ExpenseCategoryController)->store($this->request(['name' => 'Duplicate', 'code' => ' abc ']));
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already used', $result['msg']);
        $this->assertSame(1, ExpenseCategory::count());
    }

    public function test_edit_keeps_own_code_but_cannot_take_another_categorys_code()
    {
        ExpenseCategory::create(['business_id' => 1, 'name' => 'First', 'code' => '100']);
        $second = ExpenseCategory::create(['business_id' => 1, 'name' => 'Second', 'code' => '101']);
        $request = $this->request(['name' => 'Renamed', 'code' => '101']);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->app->instance('request', $request);
        $controller = new ExpenseCategoryController;
        $this->assertTrue($controller->update($request, $second->id)['success']);
        $request->merge(['code' => '100']);
        $result = $controller->update($request, $second->id);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already used', $result['msg']);
        $this->assertSame('101', $second->fresh()->code);
    }

    public function test_codes_are_scoped_to_active_categories_in_the_same_business_and_blank_is_optional()
    {
        $first = ExpenseCategory::create(['business_id' => 1, 'name' => 'First', 'code' => '100']);
        ExpenseCategory::create(['business_id' => 2, 'name' => 'Other business', 'code' => '100']);
        $blank = ExpenseCategory::create(['business_id' => 1, 'name' => 'Blank', 'code' => ' ']);
        ExpenseCategory::create(['business_id' => 1, 'name' => 'Another blank', 'code' => '']);
        $this->assertNull($blank->code);
        $first->delete();
        $replacement = ExpenseCategory::create(['business_id' => 1, 'name' => 'Replacement', 'code' => '100']);
        $this->assertSame('100', $replacement->code);
    }

    public function test_import_marks_duplicate_codes_in_the_file_as_errors()
    {
        $preview = $this->previewCsv("name,code,parent_category\nFirst,ABC,\nSecond,abc,\n");
        $this->assertSame(2, $preview['summary']['error_rows']);
        $this->assertStringContainsString('more than one category in this file', $preview['rows'][0]['errors'][0]);
        $this->assertFalse($this->confirm($preview)['success']);
        $this->assertSame(0, ExpenseCategory::count());
    }

    public function test_import_rejects_code_used_by_a_different_existing_category()
    {
        ExpenseCategory::create(['business_id' => 1, 'name' => 'Original', 'code' => '100']);
        $preview = $this->previewCsv("name,code,parent_category\nDifferent name,100,\n");
        $this->assertSame(1, $preview['summary']['error_rows']);
        $this->assertStringContainsString('already used', $preview['rows'][0]['errors'][0]);
        $this->assertFalse($this->confirm($preview)['success']);
    }

    public function test_keep_ignores_incoming_code_but_update_validates_it()
    {
        ExpenseCategory::create(['business_id' => 1, 'name' => 'Original', 'code' => '100']);
        ExpenseCategory::create(['business_id' => 1, 'name' => 'Second', 'code' => '101']);
        $csv = "name,code,parent_category\nSecond,100,\nNew,102,\n";
        $keep = $this->previewCsv($csv, 'keep');
        $this->assertSame(0, $keep['summary']['error_rows']);
        $this->assertTrue($this->confirm($keep)['success']);
        $this->assertSame('101', ExpenseCategory::where('name', 'Second')->value('code'));
        $update = $this->previewCsv($csv, 'update');
        $this->assertSame(1, $update['summary']['error_rows']);
        $this->assertFalse($this->confirm($update)['success']);
    }

    public function test_code_collision_after_preview_rolls_back_the_entire_import()
    {
        $preview = $this->previewCsv("name,code,parent_category\nFirst,100,\nSecond,101,\n");
        $this->assertSame(0, $preview['summary']['error_rows']);
        ExpenseCategory::create(['business_id' => 1, 'name' => 'Added later', 'code' => '101']);
        $result = $this->confirm($preview);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already used', $result['msg']);
        $this->assertSame(1, ExpenseCategory::count());
        $this->assertSame('Added later', ExpenseCategory::first()->name);
    }
}
