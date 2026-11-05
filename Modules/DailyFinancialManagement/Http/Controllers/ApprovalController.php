<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\BusinessLocation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DailyFinancialManagement\Entities\FinancialApprovalSetting;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;
use Modules\DailyFinancialManagement\Entities\FinancialTransaction;
use Modules\DailyFinancialManagement\Services\ApprovalWorkflowService;

class ApprovalController extends Controller
{
    protected ApprovalWorkflowService $workflowService;

    public function __construct(ApprovalWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    public function index(Request $request)
    {
        $businessId = session('business.id', 1);

        $statusFilter = $request->input('status', 'pending');

        $query = FinancialTransaction::with(['category', 'location', 'account', 'creator', 'reviewer', 'attachments'])
            ->where('business_id', $businessId);

        if ($statusFilter === 'pending') {
            $query->whereIn('approval_status', ['pending', 'reviewed']);
        } elseif ($statusFilter !== 'all') {
            $query->where('approval_status', $statusFilter);
        }

        if ($request->filled('location_id')) {
            $query->where('business_location_id', $request->location_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $branches = BusinessLocation::where('business_id', $businessId)->get();

        return view('dailyfinancialmanagement::approvals.index', compact('transactions', 'branches', 'statusFilter'));
    }

    public function handleAction(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|string|in:submit,review,approve,reject,reverse',
            'comments' => 'nullable|string|max:500',
        ]);

        $businessId = session('business.id', 1);
        $transaction = FinancialTransaction::where('business_id', $businessId)->findOrFail($id);
        $action = $request->action;
        $actor = auth()->user();
        $comments = $request->comments;

        try {
            switch ($action) {
                case 'submit':
                    $this->workflowService->submit($transaction, $actor, $comments);
                    $msg = "Transaction #{$transaction->ref_no} submitted for approval.";
                    break;
                case 'review':
                    $this->workflowService->review($transaction, $actor, $comments);
                    $msg = "Transaction #{$transaction->ref_no} marked as reviewed.";
                    break;
                case 'approve':
                    $this->workflowService->approve($transaction, $actor, $comments);
                    $msg = "Transaction #{$transaction->ref_no} approved successfully!";
                    break;
                case 'reject':
                    if (empty($comments)) {
                        return back()->with('status', ['success' => 0, 'msg' => 'A rejection reason is strictly required.']);
                    }
                    $this->workflowService->reject($transaction, $actor, $comments);
                    $msg = "Transaction #{$transaction->ref_no} rejected.";
                    break;
                case 'reverse':
                    if (empty($comments)) {
                        return back()->with('status', ['success' => 0, 'msg' => 'A reversal reason is strictly required.']);
                    }
                    $reversal = $this->workflowService->reverse($transaction, $actor, $comments);
                    $msg = "Transaction reversed. Reversal entry created: #{$reversal->ref_no}";
                    break;
                default:
                    throw new Exception("Unknown action: {$action}");
            }

            return back()->with('status', ['success' => 1, 'msg' => $msg]);
        } catch (Exception $e) {
            return back()->with('status', ['success' => 0, 'msg' => 'Action failed: ' . $e->getMessage()]);
        }
    }

    public function settings()
    {
        $businessId = session('business.id', 1);

        $settings = FinancialApprovalSetting::firstOrCreate(
            ['business_id' => $businessId, 'business_location_id' => null],
            [
                'cashier_limit' => 50.0000,
                'branch_manager_limit' => 500.0000,
                'accountant_limit' => 2000.0000,
                'general_manager_limit' => 10000.0000,
                'require_accountant_review' => true,
                'require_manager_approval' => true,
                'auto_approve_pos_sync' => true,
            ]
        );

        return view('dailyfinancialmanagement::approvals.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'cashier_limit' => 'required|numeric|min:0',
            'branch_manager_limit' => 'required|numeric|min:0',
            'accountant_limit' => 'required|numeric|min:0',
            'general_manager_limit' => 'required|numeric|min:0',
        ]);

        $businessId = session('business.id', 1);

        $settings = FinancialApprovalSetting::firstOrNew([
            'business_id' => $businessId,
            'business_location_id' => null,
        ]);

        $old = $settings->toArray();

        $settings->fill([
            'cashier_limit' => (float) $request->cashier_limit,
            'branch_manager_limit' => (float) $request->branch_manager_limit,
            'accountant_limit' => (float) $request->accountant_limit,
            'general_manager_limit' => (float) $request->general_manager_limit,
            'require_accountant_review' => (bool) $request->has('require_accountant_review'),
            'require_manager_approval' => (bool) $request->has('require_manager_approval'),
            'auto_approve_pos_sync' => (bool) $request->has('auto_approve_pos_sync'),
        ]);

        $settings->save();

        FinancialAuditLog::record(
            $businessId,
            'approval_settings_updated',
            'FinancialApprovalSetting',
            $settings->id,
            $old,
            $settings->toArray(),
            'Updated financial approval threshold limits'
        );

        return back()->with('status', ['success' => 1, 'msg' => 'Approval limits updated successfully!']);
    }
}

