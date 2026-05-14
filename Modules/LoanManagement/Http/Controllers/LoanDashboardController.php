<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Services\LoanDashboardService;

class LoanDashboardController extends Controller
{
    protected LoanDashboardService $service;

    public function __construct(LoanDashboardService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $this->service->getFilters($request);

        $locations = $this->simpleOptions('loans', 'business_location_id');
        $statuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted'];
        $collectors = $this->simpleOptions('loans', 'collector_id');
        $currencies = ['USD', 'KHR'];
        $paymentMethods = [];
        if (Schema::hasTable('payment_methods')) {
            $paymentMethods = DB::table('payment_methods')
                ->select('id', 'name')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->all();
        }

        $quickCards = $this->service->getQuickCards($filters);
        $recentPayments = $this->service->getRecentPayments($filters);
        $overdueCustomers = $this->service->getOverdueCustomers($filters);
        $visitSchedule = $this->service->getFollowUpCustomers($filters);
        $collectorPerformance = $this->service->getCollectorPerformanceChart($filters);
        $loanStatusChart = $this->service->getLoanStatusChart($filters);

        return view('loanmanagement::dashboard.index', compact(
            'filters',
            'locations',
            'statuses',
            'collectors',
            'currencies',
            'paymentMethods',
            'quickCards',
            'recentPayments',
            'overdueCustomers',
            'visitSchedule',
            'collectorPerformance',
            'loanStatusChart'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        try {
            $payload = $this->service->getDashboardData($request);
        } catch (\Throwable $e) {
            Log::error('Loan dashboard data load failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $payload = [
                'cards' => [],
                'charts' => [],
                'tables' => [],
            ];

            return response()->json([
                'success' => false,
                'message' => 'Dashboard data loaded with empty fallback',
                'data' => $payload,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data loaded successfully',
            'data' => $payload,
        ]);
    }

    protected function simpleOptions(string $table, string $column, bool $stringLabel = false): array
    {
        if (! $this->service->tableExists($table) || ! $this->service->columnExists($table, $column)) {
            return [];
        }

        $rows = DB::connection('mysql_loan')->table($table)
            ->whereNotNull($column)
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->limit(200)
            ->get();

        return $rows->map(function ($row) use ($column, $stringLabel) {
            $value = $row->{$column};

            return [
                'id' => $value,
                'name' => $stringLabel ? (string) $value : 'ID #'.$value,
            ];
        })->values()->all();
    }
}
