<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DailyFinancialManagement\Entities\FinancialAuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $businessId = session('business.id', 1);

        $query = FinancialAuditLog::with('user')
            ->where('business_id', $businessId);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        $users = User::where('business_id', $businessId)->get();

        return view('dailyfinancialmanagement::audit.index', compact('logs', 'users'));
    }
}

