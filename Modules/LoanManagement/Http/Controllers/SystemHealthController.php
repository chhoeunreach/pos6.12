<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\SystemHealthCheckService;

class SystemHealthController extends Controller
{
    public function status(Request $request)
    {
        $report = SystemHealthCheckService::check();

        return view('loanmanagement::system.status', compact('report'));
    }

    public function data(Request $request): JsonResponse
    {
        $report = SystemHealthCheckService::check();

        return response()->json([
            'success' => true,
            'report' => $report,
        ]);
    }
}
