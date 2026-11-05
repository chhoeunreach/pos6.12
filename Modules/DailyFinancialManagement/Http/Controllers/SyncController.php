<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DailyFinancialManagement\Entities\FinancialPosImport;
use Modules\DailyFinancialManagement\Services\PosFinancialSyncService;

class SyncController extends Controller
{
    protected PosFinancialSyncService $syncService;

    public function __construct(PosFinancialSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    public function index()
    {
        $businessId = session('business.id', 1);

        $imports = FinancialPosImport::with('executor')
            ->where('business_id', $businessId)
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('dailyfinancialmanagement::sync.index', compact('imports'));
    }

    public function triggerSync(Request $request)
    {
        $businessId = session('business.id', 1);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $force = (bool) $request->has('force');

        try {
            $res = $this->syncService->syncPosAndLoans($businessId, $startDate, $endDate, $force);

            if ($res['success']) {
                $msg = sprintf(
                    'Sync completed! Sales: %d, Returns: %d, Expenses: %d, Loan Payments: %d, Skipped: %d',
                    $res['sales_count'] ?? 0,
                    $res['returns_count'] ?? 0,
                    $res['expenses_count'] ?? 0,
                    $res['loan_payments_count'] ?? 0,
                    $res['skipped_count'] ?? 0
                );
                return back()->with('status', ['success' => 1, 'msg' => $msg]);
            }

            return back()->with('status', ['success' => 0, 'msg' => 'Sync failed: ' . ($res['error'] ?? 'Unknown error')]);
        } catch (Exception $e) {
            return back()->with('status', ['success' => 0, 'msg' => 'Sync exception: ' . $e->getMessage()]);
        }
    }
}

