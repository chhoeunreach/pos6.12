<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
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
        if ($request->has('lang') && in_array($request->query('lang'), ['en', 'km'], true)) {
            session(['user.language' => $request->query('lang')]);
        }

        $filters = $this->service->getFilters($request);

        $locations = $this->locationOptions();
        $statuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted'];
        $collectors = $this->simpleOptions('loans', 'collector_id');
        $currencies = ['USD', 'KHR'];
        $paymentMethods = [];
        try {
            if (Schema::hasTable('payment_methods')) {
                $paymentMethods = DB::table('payment_methods')
                    ->select('id', 'name')
                    ->where('is_active', 1)
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                    ->all();
            }
        } catch (\Throwable $e) {
            $paymentMethods = [];
        }

        try {
            $dashboard = Cache::remember('loan_dashboard_index_'.auth()->id().'_'.md5(json_encode($filters)), 300, function () use ($filters) {
                return [
                    'quickCards' => $this->service->getQuickCards($filters),
                    'recentPayments' => $this->service->getRecentPayments($filters),
                    'overdueCustomers' => $this->service->getOverdueCustomers($filters),
                    'visitSchedule' => $this->service->getFollowUpCustomers($filters),
                    'collectorPerformance' => $this->service->getCollectorPerformanceChart($filters),
                    'loanStatusChart' => $this->service->getLoanStatusChart($filters),
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Loan dashboard index cache/query failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $dashboard = [
                'quickCards' => [],
                'recentPayments' => [],
                'overdueCustomers' => [],
                'visitSchedule' => [],
                'collectorPerformance' => [],
                'loanStatusChart' => ['labels' => [], 'series' => []],
            ];
        }

        $quickCards = $dashboard['quickCards'] ?? [];
        $recentPayments = $dashboard['recentPayments'] ?? [];
        $overdueCustomers = $dashboard['overdueCustomers'] ?? [];
        $visitSchedule = $dashboard['visitSchedule'] ?? [];
        $collectorPerformance = $dashboard['collectorPerformance'] ?? [];
        $loanStatusChart = $dashboard['loanStatusChart'] ?? ['labels' => [], 'series' => []];
        $recentChats = $this->getRecentChats();

        $systemHealth = null;
        try {
            $systemHealth = \Modules\LoanManagement\Services\SystemHealthCheckService::check();
        } catch (\Throwable $e) {
            // Safe fallback if health check throws
        }

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
            'loanStatusChart',
            'recentChats',
            'systemHealth'
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

    public function quickSearch(Request $request): JsonResponse
    {
        $scope = trim((string) $request->input('scope', 'loan'));
        $term = trim((string) $request->input('q', ''));
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        if ($scope === 'sell') {
            $rows = [];
        } elseif ($request->filled('loan_id')) {
            $loanId = (int) $request->input('loan_id');
            $rows = collect($this->service->searchLoansForDashboard((string) $loanId, 25, $locationId))
                ->filter(fn ($row) => (int) ($row['id'] ?? 0) === $loanId)
                ->values()
                ->all();
        } else {
            $rows = $this->service->searchLoansForDashboard($term, 10, $locationId);
        }

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    protected function simpleOptions(string $table, string $column, bool $stringLabel = false): array
    {
        try {
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
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function locationOptions(): array
    {
        try {
            if ($this->service->tableExists('loan_business_locations')) {
                $query = DB::connection('mysql_loan')->table('loan_business_locations')
                    ->selectRaw('id, COALESCE(NULLIF(name, ""), CONCAT("Location #", id)) as name')
                    ->orderBy('name');

                if ($this->service->columnExists('loan_business_locations', 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }

                return $query->get()
                    ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                    ->all();
            }

            if ($this->service->tableExists('loans') && $this->service->columnExists('loans', 'business_location_id')) {
                $nameColumn = $this->service->columnExists('loans', 'business_location_name_snapshot')
                    ? 'business_location_name_snapshot'
                    : null;

                $query = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('business_location_id')
                    ->select('business_location_id as id');

                if ($nameColumn) {
                    $query->addSelect($nameColumn.' as name')
                        ->whereNotNull($nameColumn)
                        ->where($nameColumn, '!=', '')
                        ->groupBy('business_location_id', $nameColumn)
                        ->orderBy($nameColumn);
                } else {
                    $query->selectRaw('business_location_id as id, CONCAT("Location #", business_location_id) as name')
                        ->groupBy('business_location_id')
                        ->orderBy('business_location_id');
                }

                return $query->limit(200)->get()
                    ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                    ->all();
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    protected function getRecentChats(): array
    {
        try {
            if (! $this->service->tableExists('loan_chat_threads')) {
                return [];
            }

            $query = DB::connection('mysql_loan')
                ->table('loan_chat_threads')
                ->select([
                    'id',
                    'display_name',
                    'display_subtitle',
                    'status',
                    'priority',
                    'assigned_team',
                    'last_message',
                    'last_message_at',
                    'unread_staff_count',
                ])
                ->orderByRaw('CASE WHEN last_message_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->limit(10);

            return $query->get()->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'display_name' => (string) ($row->display_name ?: 'Customer Chat'),
                    'display_subtitle' => (string) ($row->display_subtitle ?: ''),
                    'status' => (string) ($row->status ?: 'open'),
                    'priority' => (string) ($row->priority ?: 'normal'),
                    'assigned_team' => (string) ($row->assigned_team ?: ''),
                    'last_message' => (string) ($row->last_message ?: ''),
                    'last_message_at' => ! empty($row->last_message_at) ? (string) $row->last_message_at : null,
                    'unread_count' => (int) ($row->unread_staff_count ?? 0),
                ];
            })->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
