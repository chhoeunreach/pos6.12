<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Requests\MobileApi\StoreExpenseRequest;
use App\Http\Requests\MobileApi\UpdateExpenseRequest;
use App\Http\Resources\Mobile\ExpenseCategoryResource;
use App\Http\Resources\Mobile\ExpenseResource;
use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Expenses
 * Expense management
 */
class ExpenseController extends BaseController
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'expense')
            ->with(['location', 'transaction_for', 'payment_lines']);

        $permitted_locations = $this->getPermittedLocations();
        if ($permitted_locations != 'all') {
            $query->whereIn('location_id', $permitted_locations);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $expenses = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success(ExpenseResource::collection($expenses));
    }

    public function categories()
    {
        $business_id = $this->getBusinessId();

        $categories = \App\ExpenseCategory::where('business_id', $business_id)
            ->whereNull('parent_id')
            ->with('sub_categories')
            ->get();

        return $this->success(ExpenseCategoryResource::collection($categories));
    }

    public function store(StoreExpenseRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        if (!$this->checkLocationAccess($request->location_id)) {
            return $this->unauthorized('Invalid location');
        }

        try {
            DB::beginTransaction();

            $expense_data = $request->only([
                'location_id', 'expense_category_id', 'expense_for', 'ref_no',
                'transaction_date', 'final_total', 'tax_id', 'additional_notes',
            ]);
            $expense_data['business_id'] = $business_id;
            $expense_data['created_by'] = $user_id;
            $expense_data['type'] = 'expense';
            $expense_data['status'] = 'final';
            $expense_data['payment_status'] = 'due';

            $transaction = $this->transactionUtil->createExpense($expense_data, $business_id, $user_id, false);

            if ($request->has('payment') && !empty($request->payment[0]['amount'])) {
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $request->payment, $business_id, $user_id);
                $this->transactionUtil->updatePaymentStatus($transaction->id);
            }

            DB::commit();

            $transaction->load(['location', 'transaction_for', 'payment_lines']);

            return $this->success(new ExpenseResource($transaction), 'Expense created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create expense: ' . $e->getMessage(), 500);
        }
    }

    public function update($id, UpdateExpenseRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $expense = Transaction::where('business_id', $business_id)
            ->where('type', 'expense')
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            $expense_data = $request->only([
                'location_id', 'expense_category_id', 'expense_for',
                'transaction_date', 'final_total', 'additional_notes',
            ]);
            $expense_data['business_id'] = $business_id;

            $this->transactionUtil->updateExpense($expense_data, $id, $business_id, false);

            DB::commit();

            $expense->fresh()->load(['location', 'transaction_for', 'payment_lines']);

            return $this->success(new ExpenseResource($expense), 'Expense updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update expense: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $business_id = $this->getBusinessId();

        $expense = Transaction::where('business_id', $business_id)
            ->where('type', 'expense')
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            foreach ($expense->payment_lines as $payment) {
                \App\TransactionPayment::deletePayment($payment);
            }

            $expense->delete();

            DB::commit();

            return $this->success(null, 'Expense deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete expense: ' . $e->getMessage(), 500);
        }
    }
}
