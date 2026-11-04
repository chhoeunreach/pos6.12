<?php

namespace App\Http\Controllers;

use App\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $expense_category = ExpenseCategory::where('business_id', $business_id)
                        ->select(['name', 'code', 'id', 'parent_id']);

            return Datatables::of($expense_category)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'App\Http\Controllers\ExpenseCategoryController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".expense_category_modal"><i class="glyphicon glyphicon-edit"></i>  @lang("messages.edit")</button>
                        &nbsp;
                        <button data-href="{{action(\'App\Http\Controllers\ExpenseCategoryController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_expense_category"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>'
                )
                ->editColumn('name', function ($row) {
                    if (! empty($row->parent_id)) {
                        return '--'.$row->name;
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('parent_id')
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('expense_category.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $categories = ExpenseCategory::where('business_id', $business_id)
                        ->whereNull('parent_id')
                        ->pluck('name', 'id');

        return view('expense_category.create')->with(compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'code']);
            $input['business_id'] = $request->session()->get('user.business_id');

            if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                $input['parent_id'] = $request->input('parent_id');
            }

            ExpenseCategory::create($input);
            $output = ['success' => true,
                'msg' => __('expense.added_success'),
            ];
        } catch (ValidationException $e) {
            $output = ['success' => false, 'msg' => collect($e->errors())->flatten()->first()];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ExpenseCategory  $expenseCategory
     * @return \Illuminate\Http\Response
     */
    public function show(ExpenseCategory $expenseCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $expense_category = ExpenseCategory::where('business_id', $business_id)->find($id);

            $categories = ExpenseCategory::where('business_id', $business_id)
                        ->whereNull('parent_id')
                        ->pluck('name', 'id');

            return view('expense_category.edit')
                    ->with(compact('expense_category', 'categories'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'code']);
                $business_id = $request->session()->get('user.business_id');

                $expense_category = ExpenseCategory::where('business_id', $business_id)->findOrFail($id);
                $expense_category->name = $input['name'];
                $expense_category->code = $input['code'];

                if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                    $expense_category->parent_id = $request->input('parent_id');
                } else {
                    $expense_category->parent_id = null;
                }

                $expense_category->save();

                $output = ['success' => true,
                    'msg' => __('expense.updated_success'),
                ];
            } catch (ValidationException $e) {
                $output = ['success' => false, 'msg' => collect($e->errors())->flatten()->first()];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $expense_category = ExpenseCategory::where('business_id', $business_id)->findOrFail($id);
                $expense_category->delete();

                //delete sub categories also
                ExpenseCategory::where('business_id', $business_id)->where('parent_id', $id)->delete();

                $output = ['success' => true,
                    'msg' => __('expense.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function bulkDestroy(Request $request)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'ids' => 'required|array|min:1|max:10000',
            'ids.*' => 'required|integer|distinct',
        ]);
        $business_id = $request->session()->get('user.business_id');

        try {
            $count = \DB::transaction(function () use ($data, $business_id) {
                $ids = ExpenseCategory::where('business_id', $business_id)
                    ->whereIn('id', $data['ids'])->lockForUpdate()->pluck('id')->all();
                if (count($ids) !== count($data['ids'])) {
                    throw ValidationException::withMessages([
                        'ids' => __('expense.selected_categories_unavailable'),
                    ]);
                }

                // Match single deletion: deleting a parent also removes its children.
                return ExpenseCategory::where('business_id', $business_id)
                    ->where(function ($query) use ($ids) {
                        $query->whereIn('id', $ids)->orWhereIn('parent_id', $ids);
                    })->delete();
            });

            return ['success' => true, 'msg' => __('expense.categories_bulk_deleted', ['count' => $count])];
        } catch (ValidationException $e) {
            return ['success' => false, 'msg' => collect($e->errors())->flatten()->first()];
        } catch (\Exception $e) {
            Log::error('Expense category bulk deletion failed', ['business_id' => $business_id, 'error' => $e->getMessage()]);

            return ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }
    }

    public function getSubCategories(Request $request)
    {
        if (! empty($request->input('cat_id'))) {
            $category_id = $request->input('cat_id');
            $business_id = $request->session()->get('user.business_id');
            $sub_categories = ExpenseCategory::where('business_id', $business_id)
                        ->where('parent_id', $category_id)
                        ->select(['name', 'id'])
                        ->get();
        }

        $html = '<option value="">'.__('lang_v1.none').'</option>';
        if (! empty($sub_categories)) {
            foreach ($sub_categories as $sub_category) {
                $html .= '<option value="'.$sub_category->id.'">'.$sub_category->name.'</option>';
            }
        }
        return response($html);
    }

    public function downloadTemplate()
    {
        $columns = ['name', 'code', 'parent_category'];

        $filename = 'expense_categories_template_' . date('Ymd_His') . '.csv';

        $callback = function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    public function export(Request $request)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $format = strtolower((string) $request->input('format', 'csv'));
        $format = in_array($format, ['csv', 'xlsx'], true) ? $format : 'csv';

        $categories = ExpenseCategory::where('business_id', $business_id)
            ->select(['name', 'code', 'id', 'parent_id'])
            ->get();

        $parent_names = ExpenseCategory::where('business_id', $business_id)
            ->whereNull('parent_id')
            ->pluck('name', 'id');

        $rows = [];
        foreach ($categories as $cat) {
            $rows[] = [
                'name' => $cat->name,
                'code' => $cat->code,
                'parent_category' => $cat->parent_id ? ($parent_names[$cat->parent_id] ?? '') : '',
            ];
        }

        $filename = 'expense_categories_' . date('Ymd_His') . '.' . $format;

        if ($format === 'xlsx') {
            return Excel::download(new \App\Exports\ArrayExport($rows), $filename);
        }

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys($rows[0] ?? ['name' => null, 'code' => null, 'parent_category' => null]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    public function importPreview(Request $request)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'file' => 'required|file|max:5120',
            'existing_action' => 'sometimes|required|in:keep,update',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $existing_action = $request->input('existing_action', 'keep');

        try {
            $sheets = Excel::toArray(new \App\Imports\ExpenseCategoriesImport, $request->file('file'));
            $rawRows = $sheets[0] ?? [];
            $headings = array_shift($rawRows) ?? [];
            $aliases = [
                'name' => 'name', 'category_name' => 'name', 'ឈ្មោះប្រភេទ' => 'name',
                'code' => 'code', 'category_code' => 'code', 'លេខកូដ' => 'code',
                'parent_category' => 'parent_category', 'ប្រភេទមេ' => 'parent_category',
            ];
            $columns = [];
            foreach ($headings as $index => $heading) {
                $heading = trim(str_replace("\xEF\xBB\xBF", '', (string) $heading));
                $key = preg_replace('/\s+/u', '_', mb_strtolower($heading));
                if (isset($aliases[$key])) {
                    $columns[$aliases[$key]] = $index;
                }
            }
            if (! array_key_exists('name', $columns)) {
                return ['success' => false, 'msg' => 'Category name column not found. Use name, Category name, or ឈ្មោះប្រភេទ in the first row of the first sheet.'];
            }

            $normalizedRows = [];
            $names = [];
            foreach ($rawRows as $i => $row) {
                if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }
                $data = [];
                foreach ($columns as $key => $index) {
                    $data[$key] = trim((string) ($row[$index] ?? ''));
                }
                $row = $data;
                $name = $row['name'];
                $names[] = $name;
                $normalizedRows[] = [
                    'row_number' => $i + 2,
                    'data' => $row,
                ];
            }

            if (empty($normalizedRows)) {
                return ['success' => false, 'msg' => 'No categories found in the first sheet. Add category rows below the headings and preview again.'];
            }

            $existingByName = ExpenseCategory::where('business_id', $business_id)
                ->whereIn('name', array_values(array_unique(array_filter($names))))
                ->get()
                ->keyBy('name');

            $parentCategories = ExpenseCategory::where('business_id', $business_id)
                ->whereNull('parent_id')
                ->pluck('id', 'name');

            // Resolve parents from the entire upload so new parents work
            // regardless of their row order.
            $uploadedRoots = [];
            foreach ($normalizedRows as $rowInfo) {
                $data = $rowInfo['data'];
                $name = trim((string) $data['name']);
                $existing = $existingByName[$name] ?? null;
                $uploadedRoots[$name] = $existing_action === 'keep' && $existing
                    ? $existing->parent_id === null
                    : trim((string) ($data['parent_category'] ?? '')) === '';
            }

            $uploadedCodeNames = [];
            foreach ($normalizedRows as $rowInfo) {
                $data = $rowInfo['data'];
                $name = trim((string) $data['name']);
                if ($existing_action === 'keep' && isset($existingByName[$name])) {
                    continue;
                }
                $code = mb_strtolower(trim((string) ($data['code'] ?? '')));
                if ($code !== '') {
                    $uploadedCodeNames[$code][$name] = true;
                }
            }

            $summary = [
                'total_rows' => count($normalizedRows),
                'new_rows' => 0,
                'existing_rows' => 0,
                'skipped_rows' => 0,
                'error_rows' => 0,
            ];

            $previewRows = [];
            foreach ($normalizedRows as $rowInfo) {
                $row = $rowInfo['data'];
                $row_no = $rowInfo['row_number'];
                $errors = [];

                $name = trim((string) ($row['name'] ?? ''));
                $code = trim((string) ($row['code'] ?? ''));
                $parent_name = trim((string) ($row['parent_category'] ?? ''));

                if ($name === '') {
                    $errors[] = 'name is required';
                }

                $existing = $existingByName[$name] ?? null;
                $exists = ! empty($existing);
                $keepExisting = $exists && $existing_action === 'keep';

                if (! $keepExisting && $code !== '') {
                    if (count($uploadedCodeNames[mb_strtolower($code)] ?? []) > 1) {
                        $errors[] = __('expense.category_code_duplicate_in_file', ['code' => $code]);
                    }
                    if (ExpenseCategory::codeIsTaken($business_id, $code, $existing ? $existing->id : null)) {
                        $errors[] = __('expense.category_code_taken', ['code' => $code]);
                    }
                }

                $parent_id = null;
                if (! $keepExisting && $parent_name !== '') {
                    $parent_id = $parentCategories[$parent_name] ?? null;
                    if (array_key_exists($parent_name, $uploadedRoots)) {
                        if (! $uploadedRoots[$parent_name]) {
                            $errors[] = "parent_category '$parent_name' must be a top-level category";
                        }
                    } elseif ($parent_id === null) {
                        $errors[] = "parent_category '$parent_name' not found";
                    }
                }

                $status = $exists ? 'existing' : 'new';

                if (! empty($errors)) {
                    $status = 'error';
                }

                if ($status === 'error') {
                    $summary['error_rows']++;
                } elseif ($exists) {
                    $summary['existing_rows']++;
                    if ($keepExisting) {
                        $summary['skipped_rows']++;
                    }
                } else {
                    $summary['new_rows']++;
                }

                $previewRows[] = [
                    'row_number' => $row_no,
                    'name' => $name,
                    'code' => $code,
                    'parent_category' => $parent_name,
                    'status' => $status,
                    'errors' => $errors,
                    'parsed' => [
                        'parent_id' => $parent_id,
                    ],
                    'data' => $row,
                ];
            }

            $token = Str::random(40);
            Cache::put('exp_cat_import_preview:' . $token, [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'rows' => $previewRows,
                'existing_action' => $existing_action,
                'created_at' => now()->toDateTimeString(),
            ], now()->addMinutes(30));

            Log::info('Expense category import preview', [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'total_rows' => $summary['total_rows'],
                'error_rows' => $summary['error_rows'],
            ]);

            return [
                'success' => true,
                'token' => $token,
                'summary' => $summary,
                'existing_action' => $existing_action,
                'rows' => $previewRows,
            ];
        } catch (\Exception $e) {
            Log::emergency('Expense cat import preview error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }

    public function importConfirm(Request $request)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'token' => 'required|string',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');
        $token = $request->input('token');

        $payload = Cache::get('exp_cat_import_preview:' . $token);
        if (empty($payload) || ($payload['business_id'] ?? null) !== $business_id || ($payload['user_id'] ?? null) !== $user_id) {
            return ['success' => false, 'msg' => 'Import preview expired. Please preview again.'];
        }

        $existing_action = $payload['existing_action'] ?? 'keep';
        $rows = $payload['rows'] ?? [];
        $errorRows = array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'error');
        if (! empty($errorRows)) {
            return ['success' => false, 'msg' => 'Fix import errors in preview before confirming.'];
        }

        try {
            $result = \DB::transaction(function () use ($rows, $business_id, $existing_action) {
                $updated = 0;
                $inserted = 0;
                $skipped = 0;

                $existingByName = ExpenseCategory::where('business_id', $business_id)
                    ->get()
                    ->keyBy('name');

                $parentCategories = ExpenseCategory::where('business_id', $business_id)
                    ->whereNull('parent_id')
                    ->pluck('id', 'name');

                // Save roots first, then resolve children to their real IDs.
                $isRoot = fn ($row) => trim((string) ($row['data']['parent_category'] ?? '')) === '';
                $orderedRows = array_merge(
                    array_filter($rows, $isRoot),
                    array_filter($rows, fn ($row) => ! $isRoot($row))
                );

                foreach ($orderedRows as $row) {
                    $data = $row['data'] ?? [];
                    $name = trim((string) ($data['name'] ?? ''));
                    if ($name === '') {
                        $skipped++;
                        continue;
                    }

                    $existing = $existingByName[$name] ?? null;
                    if ($existing && $existing_action === 'keep') {
                        $skipped++;
                        continue;
                    }

                    $code = trim((string) ($data['code'] ?? ''));
                    $parent_name = trim((string) ($data['parent_category'] ?? ''));
                    $parent_id = null;
                    if ($parent_name !== '') {
                        $parent_id = $parentCategories[$parent_name] ?? null;
                        if ($parent_id === null) {
                            throw new \RuntimeException("parent_category '$parent_name' no longer exists. Please preview again.");
                        }
                    }

                    if ($existing) {
                        $existing->code = $code;
                        $existing->parent_id = $parent_id;
                        $existing->save();
                        $updated++;
                    } else {
                        $existing = ExpenseCategory::create([
                            'name' => $name,
                            'code' => $code,
                            'parent_id' => $parent_id,
                            'business_id' => $business_id,
                        ]);
                        $inserted++;
                    }

                    $existingByName[$name] = $existing;
                    if ($parent_name === '') {
                        $parentCategories[$name] = $existing->id;
                    } else {
                        unset($parentCategories[$name]);
                    }
                }

                return compact('inserted', 'updated', 'skipped');
            });

            Cache::forget('exp_cat_import_preview:' . $token);

            Log::info('Expense category import confirm', [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'result' => $result,
            ]);

            return [
                'success' => true,
                'msg' => "Import completed. Inserted: {$result['inserted']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}",
            ];
        } catch (ValidationException $e) {
            return ['success' => false, 'msg' => collect($e->errors())->flatten()->first()];
        } catch (\Exception $e) {
            Log::emergency('Expense cat import confirm error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }
}
