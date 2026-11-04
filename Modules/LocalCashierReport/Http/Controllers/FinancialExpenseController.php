<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\Events\ExpenseCreatedOrModified;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\LocalCashierReport\Services\FinancialDashboardReport;
use Modules\LocalCashierReport\Support\ReportLanguage;
use Spatie\Activitylog\Models\Activity;

class FinancialExpenseController extends Controller
{
    public function __construct(
        private FinancialDashboardController $dashboard,
        private FinancialDashboardReport $report,
        private TransactionUtil $transactions,
        private ModuleUtil $modules
    ) {
    }

    private function connection(string $source): string
    {
        $connections = ['main' => config('database.default'), 'accessory' => config('accessory.database_connection', 'accessory'), 'service' => config('service.database_connection', 'service')];
        abort_unless(isset($connections[$source]), 404);

        return $connections[$source];
    }

    private function locationIds($locations, array $filters, string $source): array
    {
        $ids = [];
        foreach ($locations->whereIn('id', $filters['location_ids']) as $location) {
            $id = (int) $location->id;
            $match = $source === 'main' ? $id > 0 : ($id < -1 && (-$id % 2) === ($source === 'service' ? 1 : 0));
            if ($match) $ids[] = $source === 'main' ? $id : intdiv(-$id, 2);
        }

        return $ids;
    }

    private function expenseAccess(Request $request, string $source): void
    {
        abort_unless($request->user()->can('all_expense.access') || $request->user()->can('view_own_expense'), 403);
        // User IDs in separate applications are independent. Own-only access cannot cross sources.
        abort_if($source !== 'main' && ! $request->user()->can('all_expense.access'), 403);
    }

    private function ownExpenses($query, Request $request, string $prefix = 't.')
    {
        if (! $request->user()->can('all_expense.access')) {
            $query->where(function ($own) use ($request, $prefix) {
                $own->where($prefix.'created_by', $request->user()->id)->orWhere($prefix.'expense_for', $request->user()->id);
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        [$businessId, $locations, $filters] = $this->dashboard->context($request);
        $request->validate(['page' => 'nullable|integer|min:1|max:2000']);
        $page = (int) $request->input('page', 1);
        $perPage = 25;
        $rows = collect();
        $count = 0;
        $amount = 0;
        foreach (['main', 'accessory', 'service'] as $source) {
            if ($filters['source'] !== 'all' && $filters['source'] !== $source) continue;
            $ids = $this->locationIds($locations, $filters, $source);
            if (! $ids) continue;
            $this->expenseAccess($request, $source);
            $query = $this->ownExpenses($this->report->expenseQuery($this->connection($source), $businessId, $ids, $filters), $request);
            $summary = (clone $query)->selectRaw("COUNT(*) as count, COALESCE(SUM(CASE WHEN t.type = 'expense_refund' THEN -t.final_total ELSE t.final_total END), 0) as amount")->first();
            $count += (int) $summary->count;
            $amount += (float) $summary->amount;
            $records = (clone $query)->leftJoin('business_locations as location', 'location.id', '=', 't.location_id')
                ->select('t.id', 't.transaction_date', 't.ref_no', 't.type', 't.final_total', 't.additional_notes', 'location.name as location_name')
                ->selectRaw("COALESCE(root.name, ec.name, '__uncategorized__') as category_name")
                ->orderByDesc('t.transaction_date')->orderByDesc('t.id')->limit($page * $perPage)->get();
            foreach ($records as $row) {
                $row->source = $source;
                $row->amount = (float) $row->final_total * ($row->type === 'expense_refund' ? -1 : 1);
                $row->edit_url = $request->user()->can('expense.edit') ? route('reports.financial-expenses.edit', [$source, $row->id]) : null;
                $rows->push($row);
            }
        }
        $rows = $rows->sortBy(fn ($row) => $row->transaction_date.'|'.str_pad($row->id, 20, '0', STR_PAD_LEFT).'|'.$row->source, SORT_REGULAR, true)
            ->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json(['rows' => $rows, 'total' => $count, 'amount' => $amount, 'page' => $page, 'last_page' => max(1, (int) ceil($count / $perPage))]);
    }

    private function expense(Request $request, string $source, int $id, bool $lock = false)
    {
        [$businessId, $locations] = $this->dashboard->context($request);
        $this->expenseAccess($request, $source);
        abort_unless($request->user()->can('expense.edit'), 403);
        $ids = $this->locationIds($locations, ['location_ids' => $locations->pluck('id')->all()], $source);
        $query = Transaction::on($this->connection($source))->where('business_id', $businessId)
            ->whereIn('location_id', $ids)->whereIn('type', ['expense', 'expense_refund'])->where('status', 'final');
        $this->ownExpenses($query, $request, '');
        if ($lock) $query->lockForUpdate();

        return $query->findOrFail($id);
    }

    public function edit(Request $request, string $source, int $id)
    {
        $expense = $this->expense($request, $source, $id);
        $categories = DB::connection($this->connection($source))->table('expense_categories')
            ->where('business_id', $expense->business_id)->where(function ($query) use ($expense) {
                $query->whereNull('deleted_at')->orWhereIn('id', array_filter([$expense->expense_category_id, $expense->expense_sub_category_id]));
            })->orderBy('name')->get(['id', 'name', 'parent_id']);
        $fields = $expense->only(['id', 'ref_no', 'transaction_date', 'final_total', 'additional_notes', 'expense_category_id', 'expense_sub_category_id']);
        $fields['updated_at'] = (string) $expense->updated_at;

        return response()->json([
            'expense' => $fields,
            'categories' => $categories,
            'update_url' => route('reports.financial-expenses.update', [$source, $id]),
        ]);
    }

    public function update(Request $request, string $source, int $id)
    {
        // Check permissions and ownership before validating or mutating a source database.
        $expense = $this->expense($request, $source, $id);
        $connection = $this->connection($source);
        abort_unless($this->modules->isSubscribed($expense->business_id), 403);
        $data = $request->validate([
            'ref_no' => 'nullable|string|max:191',
            'transaction_date' => 'required|date',
            'final_total' => 'required|numeric|min:0|max:999999999999',
            'additional_notes' => 'nullable|string|max:10000',
            'updated_at' => 'present|nullable|string',
            'expense_category_id' => ['nullable', 'integer', Rule::exists($connection.'.expense_categories', 'id')->where('business_id', $expense->business_id)->whereNull('parent_id')->where(function ($query) use ($expense) {
                $query->whereNull('deleted_at')->orWhere('id', $expense->expense_category_id);
            })],
            'expense_sub_category_id' => ['nullable', 'integer', Rule::prohibitedIf(! $request->filled('expense_category_id')), Rule::exists($connection.'.expense_categories', 'id')->where('business_id', $expense->business_id)->where('parent_id', $request->input('expense_category_id'))->where(function ($query) use ($expense) {
                $query->whereNull('deleted_at')->orWhere('id', $expense->expense_sub_category_id);
            })],
        ]);

        DB::connection($connection)->transaction(function () use ($request, $source, $id, $data, $connection) {
            $expense = $this->expense($request, $source, $id, true);
            abort_if((string) $expense->updated_at !== (string) ($data['updated_at'] ?? ''), 409, ReportLanguage::text('finance_expense_stale'));
            $before = clone $expense;
            // Reuse the established tax/payment calculation on the record's own source.
            // Preserve recurrence settings; this modal edits the current expense only.
            $input = $data;
            unset($input['updated_at']);
            $input['transaction_date'] = Carbon::parse($data['transaction_date'])->format('Y-m-d H:i:s');
            foreach (['recur_interval', 'recur_interval_type', 'recur_repetitions', 'subscription_repeat_on'] as $field) $input[$field] = $expense->$field;
            if ($expense->is_recurring) $input['is_recurring'] = 1;
            $updateRequest = Request::create('/', 'PUT', $input);
            DB::usingConnection($connection, function () use ($updateRequest, $expense, $before, $connection, $source, $request) {
                $updated = $this->transactions->updateExpense($updateRequest, $expense->id, $expense->business_id, false);
                $updated->setConnection($connection);
                if ($source === 'main') {
                    $this->transactions->activityLog($updated, 'edited', $before);
                } else {
                    $log = new Activity;
                    $log->setConnection($connection);
                    $log->log_name = config('activitylog.default_log_name', 'default');
                    $log->description = 'edited';
                    $log->subject_type = $updated->getMorphClass();
                    $log->subject_id = $updated->id;
                    $log->business_id = $expense->business_id;
                    $log->properties = [
                        'dashboard_actor_id' => $request->user()->id, 'dashboard_source' => 'main', 'expense_source' => $source,
                        'old' => $before->only(array_keys($updateRequest->all())),
                        'attributes' => $updated->only(array_keys($updateRequest->all())),
                    ];
                    $log->save();
                }
                event(new ExpenseCreatedOrModified($updated));
            });
        });

        return response()->json(['success' => true, 'msg' => ReportLanguage::text('finance_expense_saved')]);
    }
}
