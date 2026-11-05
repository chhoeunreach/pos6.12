<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\DailyFinancialManagement\Entities\FinancialAccount;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Entities\FinancialCategory;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Entities\FinancialTransactionAttachment;
use Modules\DailyFinancialManagement\Services\ApprovalWorkflowService;

class ExpenseController extends Controller
{
    protected ApprovalWorkflowService $approvalService;

    public function __construct(ApprovalWorkflowService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    public function index(Request $request)
    {
        $businessId = session('business.id', 1);

        $query = FinancialTransaction::with(['category', 'location', 'account', 'creator', 'approver', 'attachments'])
            ->where('business_id', $businessId)
            ->where('transaction_type', 'expense');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('location_id')) {
            $query->where('business_location_id', $request->location_id);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('is_operating')) {
            $query->where('is_operating', (bool) $request->is_operating);
        }

        $expenses = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $totalAmount = (float) (clone $query)->sum('amount_in_base_currency');

        $branches = BusinessLocation::where('business_id', $businessId)->get();
        $categories = FinancialCategory::where('business_id', $businessId)
            ->whereIn('type', ['operating_expense', 'non_operating_expense'])
            ->where('is_active', true)
            ->get();
        $accounts = FinancialAccount::where('business_id', $businessId)->where('is_active', true)->get();
        $departments = $this->getAvailableDepartments();

        return view('dailyfinancialmanagement::expense.index', compact(
            'expenses',
            'branches',
            'categories',
            'accounts',
            'departments',
            'totalAmount'
        ));
    }

    public function create()
    {
        $businessId = session('business.id', 1);

        $branches = BusinessLocation::where('business_id', $businessId)->get();
        $categories = FinancialCategory::where('business_id', $businessId)
            ->whereIn('type', ['operating_expense', 'non_operating_expense'])
            ->where('is_active', true)
            ->get();
        $accounts = FinancialAccount::where('business_id', $businessId)->where('is_active', true)->get();
        $departments = $this->getAvailableDepartments();

        return view('dailyfinancialmanagement::expense.create', compact('branches', 'categories', 'accounts', 'departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_location_id' => 'required|integer',
            'category_id' => 'required|integer',
            'financial_account_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:USD,KHR',
            'payment_method' => 'required|string',
            'description' => 'required|string|max:1000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $businessId = session('business.id', 1);
        $amount = (float) $request->amount;
        $currency = $request->currency;
        $exchangeRate = $currency === 'KHR' ? 4100.0000 : 1.0000;
        $baseAmount = $currency === 'KHR' ? ($amount / $exchangeRate) : $amount;

        try {
            DB::beginTransaction();

            $refNo = $request->ref_no ?: ('EXP-' . date('ymd') . '-' . rand(1000, 9999));
            $isOperating = (bool) $request->input('is_operating', true);

            $expense = FinancialTransaction::create([
                'business_id' => $businessId,
                'business_location_id' => $request->business_location_id,
                'department_id' => $request->department_id,
                'category_id' => $request->category_id,
                'financial_account_id' => $request->financial_account_id,
                'transaction_type' => 'expense',
                'transaction_date' => $request->transaction_date,
                'ref_no' => $refNo,
                'source_type' => 'manual',
                'description' => $request->description,
                'amount' => $amount,
                'cogs_amount' => 0.0000,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'amount_in_base_currency' => $baseAmount,
                'payment_method' => $request->payment_method,
                'approval_status' => 'draft',
                'is_operating' => $isOperating,
                'created_by' => auth()->id(),
            ]);

            // Handle file attachment
            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $path = $file->store('financial_receipts', 'public');

                FinancialTransactionAttachment::create([
                    'financial_transaction_id' => $expense->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);
            }

            // If user clicked "Save & Submit", immediately submit to workflow
            if ($request->has('submit_approval')) {
                $this->approvalService->submit($expense, auth()->user(), 'Initial expense submission');
            }

            FinancialAuditLog::record(
                $businessId,
                'expense_created',
                'FinancialTransaction',
                $expense->id,
                null,
                $expense->toArray(),
                "Created manual expense entry #{$refNo} for \${$baseAmount}"
            );

            DB::commit();

            return redirect()->route('daily-financial.expense.index')
                ->with('status', ['success' => 1, 'msg' => 'Expense entry recorded successfully!']);
        } catch (Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('status', ['success' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $businessId = session('business.id', 1);
        $expense = FinancialTransaction::with(['category', 'location', 'account', 'creator', 'reviewer', 'approver', 'attachments', 'approvals.actor'])
            ->where('business_id', $businessId)
            ->findOrFail($id);

        return view('dailyfinancialmanagement::expense.show', compact('expense'));
    }

    public function destroy($id)
    {
        $businessId = session('business.id', 1);
        $expense = FinancialTransaction::where('business_id', $businessId)->findOrFail($id);

        if ($expense->approval_status === 'approved' || $expense->is_locked) {
            return back()->with('status', ['success' => 0, 'msg' => 'Cannot delete an approved or locked transaction. Please use the reversal workflow.']);
        }

        $expense->delete();

        FinancialAuditLog::record(
            $businessId,
            'expense_deleted',
            'FinancialTransaction',
            $expense->id,
            $expense->toArray(),
            null,
            "Deleted draft/pending expense entry #{$expense->ref_no}"
        );

        return redirect()->route('daily-financial.expense.index')
            ->with('status', ['success' => 1, 'msg' => 'Expense entry removed.']);
    }

    protected function getAvailableDepartments(): array
    {
        try {
            $deps = DB::table('hr_for_draf.departments')
                ->where('is_active', 1)
                ->select(['id', 'dept_name'])
                ->get();

            return $deps->toArray();
        } catch (\Throwable $e) {
            return [
                (object) ['id' => 1, 'dept_name' => 'Sales & Showroom'],
                (object) ['id' => 2, 'dept_name' => 'Cashier & Front Desk'],
                (object) ['id' => 3, 'dept_name' => 'Technicians & Repairs'],
                (object) ['id' => 4, 'dept_name' => 'Inventory & Warehouse'],
                (object) ['id' => 5, 'dept_name' => 'Accounting & Finance'],
                (object) ['id' => 6, 'dept_name' => 'Administration & Management'],
                (object) ['id' => 7, 'dept_name' => 'Delivery & Logistics'],
            ];
        }
    }
}

