<?php

namespace Modules\Service\Http\Controllers;

use App\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
                    '<button data-href="{{action(\'Modules\Service\Http\Controllers\ExpenseCategoryController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".expense_category_modal"><i class="glyphicon glyphicon-edit"></i>  @lang("messages.edit")</button>
                        &nbsp;
                        <button data-href="{{action(\'Modules\Service\Http\Controllers\ExpenseCategoryController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_expense_category"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>'
                )
                ->editColumn('name', function ($row) {
                    if (! empty($row->parent_id)) {
                        return '--'.$row->name;
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('id')
                ->removeColumn('parent_id')
                ->rawColumns([2])
                ->make(false);
        }

        return view('service::expense_category.index');
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

        return view('service::expense_category.create')->with(compact('categories'));
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

            return view('service::expense_category.edit')
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
        echo $html;
        exit;
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
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        try {
            $sheets = Excel::toArray([], $request->file('file'));
            $rawRows = $sheets[0] ?? [];

            $normalizedRows = [];
            $names = [];
            foreach ($rawRows as $i => $row) {
                if ($i === 0) {
                    continue;
                }
                $row = array_change_key_case(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row), CASE_LOWER);
                if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $names[] = $name;
                $normalizedRows[] = [
                    'row_number' => $i + 1,
                    'data' => $row,
                ];
            }

            $existingByName = ExpenseCategory::where('business_id', $business_id)
                ->whereIn('name', array_values(array_unique(array_filter($names))))
                ->get()
                ->keyBy('name');

            $parentCategories = ExpenseCategory::where('business_id', $business_id)
                ->whereNull('parent_id')
                ->pluck('id', 'name');

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

                $parent_id = null;
                if ($parent_name !== '') {
                    $parent_id = $parentCategories[$parent_name] ?? null;
                    if ($parent_id === null) {
                        $errors[] = "parent_category '$parent_name' not found";
                    }
                }

                $existing = $existingByName[$name] ?? null;
                $exists = ! empty($existing);
                $status = $exists ? 'existing' : 'new';

                if (! empty($errors)) {
                    $status = 'error';
                }

                if ($status === 'error') {
                    $summary['error_rows']++;
                } elseif ($exists) {
                    $summary['existing_rows']++;
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

        $rows = $payload['rows'] ?? [];
        $errorRows = array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'error');
        if (! empty($errorRows)) {
            return ['success' => false, 'msg' => 'Fix import errors in preview before confirming.'];
        }

        try {
            $result = \DB::transaction(function () use ($rows, $business_id) {
                $updated = 0;
                $inserted = 0;
                $skipped = 0;

                $existingByName = ExpenseCategory::where('business_id', $business_id)
                    ->get()
                    ->keyBy('name');

                $parentCategories = ExpenseCategory::where('business_id', $business_id)
                    ->whereNull('parent_id')
                    ->pluck('id', 'name');

                foreach ($rows as $row) {
                    $data = $row['data'] ?? [];
                    $name = trim((string) ($data['name'] ?? ''));
                    if ($name === '') {
                        $skipped++;
                        continue;
                    }

                    $code = trim((string) ($data['code'] ?? ''));
                    $parent_name = trim((string) ($data['parent_category'] ?? ''));
                    $parent_id = null;
                    if ($parent_name !== '') {
                        $parent_id = $parentCategories[$parent_name] ?? null;
                    }

                    $existing = $existingByName[$name] ?? null;
                    if ($existing) {
                        $existing->code = $code;
                        $existing->parent_id = $parent_id;
                        $existing->save();
                        $updated++;
                    } else {
                        ExpenseCategory::create([
                            'name' => $name,
                            'code' => $code,
                            'parent_id' => $parent_id,
                            'business_id' => $business_id,
                        ]);
                        $inserted++;
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
        } catch (\Exception $e) {
            Log::emergency('Expense cat import confirm error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }
}
