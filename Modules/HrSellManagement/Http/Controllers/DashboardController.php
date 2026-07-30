<?php

namespace Modules\HrSellManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($this->canOpen(), 403);

        $businessId = (int) session('user.business_id');
        $today = now()->toDateString();
        $filters = [
            'start_date' => $request->input('start_date') ?: $today,
            'end_date' => $request->input('end_date') ?: $today,
            'branch_name' => $request->input('branch_name'),
            'sell_type' => $request->input('sell_type'),
        ];

        $pos = $this->posHrDashboardData($filters);
        $managed = $this->managedDashboardData($businessId, $today);

        return view('hrsellmanagement::dashboard.index', [
            'metrics' => array_merge($pos['metrics'], $managed['metrics']),
            'topHr' => $pos['topHr'],
            'topBranches' => $pos['topBranches'],
            'topSellTypes' => $pos['topSellTypes'],
            'recent' => $pos['recent'],
            'hrBranches' => $pos['branches'],
            'hrSellTypes' => $pos['sellTypes'],
            'filters' => $filters,
            'hrConnectionOk' => $pos['ok'],
            'hrConnectionMessage' => $pos['message'],
        ]);
    }

    public function salesTraffic(Request $request)
    {
        abort_unless($this->canOpen(), 403);

        $period = $request->input('period') === 'monthly' ? 'monthly' : 'daily';
        $today = now()->toDateString();
        $filters = [
            'start_date' => $request->input('start_date') ?: ($period === 'monthly' ? now()->startOfMonth()->toDateString() : $today),
            'end_date' => $request->input('end_date') ?: $today,
            'branch_name' => $request->input('branch_name'),
            'sell_type' => $request->input('sell_type'),
        ];

        $data = $this->salesTrafficData($filters, $period);

        return view('hrsellmanagement::dashboard.sales_traffic', [
            'filters' => $filters,
            'period' => $period,
            'metrics' => $data['metrics'],
            'trafficRows' => $data['trafficRows'],
            'locationCards' => $data['locationCards'],
            'hrBranches' => $data['branches'],
            'hrSellTypes' => $data['sellTypes'],
            'hrConnectionOk' => $data['ok'],
            'hrConnectionMessage' => $data['message'],
        ]);
    }

    private function posHrDashboardData(array $filters): array
    {
        try {
            $base = $this->filteredPosQuery($filters);
            $branches = $this->hrBranches();
            $sellTypes = $this->hrSellTypes();

            $metrics = [
                'pos_filtered_sales' => (float) (clone $base)->sum('total_amount'),
                'pos_filtered_count' => (int) (clone $base)->count(),
                'pos_branch_count' => (int) (clone $base)->whereNotNull('branch_name')->where('branch_name', '!=', '')->distinct()->count('branch_name'),
                'pos_seller_count' => (int) (clone $base)->where(function ($q) {
                    $q->whereNotNull('seller_name')->where('seller_name', '!=', '')
                        ->orWhereNotNull('user_id');
                })->count(DB::raw("DISTINCT COALESCE(CAST(user_id AS CHAR), NULLIF(TRIM(seller_name), ''))")),
            ];

            $metrics['pos_average_sale'] = $metrics['pos_filtered_count'] > 0
                ? round($metrics['pos_filtered_sales'] / $metrics['pos_filtered_count'], 2)
                : 0;

            $topHr = $this->filteredPosQuery($filters, 'sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->select(
                    DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as user_name"),
                    DB::raw("NULLIF(TRIM(u.username), '') as username"),
                    DB::raw("COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('seller:', TRIM(sor.seller_name))) as seller_key"),
                    DB::raw('COUNT(*) as sale_count'),
                    DB::raw('COALESCE(SUM(sor.total_amount), 0) as sale_total')
                )
                ->groupBy(
                    DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown')"),
                    DB::raw("NULLIF(TRIM(u.username), '')"),
                    DB::raw("COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('seller:', TRIM(sor.seller_name)))")
                )
                ->orderByDesc('sale_total')
                ->limit(10)
                ->get();

            $topBranches = $this->filteredPosQuery($filters)
                ->select(
                    DB::raw("COALESCE(NULLIF(TRIM(branch_name), ''), 'Unknown') as branch_name"),
                    DB::raw('COUNT(*) as sale_count'),
                    DB::raw('COALESCE(SUM(total_amount), 0) as sale_total')
                )
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(branch_name), ''), 'Unknown')"))
                ->orderByDesc('sale_total')
                ->limit(10)
                ->get();

            $topSellTypes = $this->filteredPosQuery($filters)
                ->select(
                    DB::raw($this->sellTypeSqlCase('service_type', 'key') . ' as sell_type_key'),
                    DB::raw($this->sellTypeSqlCase('service_type', 'label') . ' as sell_type_name'),
                    DB::raw('COUNT(*) as sale_count'),
                    DB::raw('COALESCE(SUM(total_amount), 0) as sale_total')
                )
                ->groupBy(
                    DB::raw($this->sellTypeSqlCase('service_type', 'key')),
                    DB::raw($this->sellTypeSqlCase('service_type', 'label'))
                )
                ->orderByDesc('sale_total')
                ->limit(10)
                ->get();

            $recent = $this->filteredPosQuery($filters, 'sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->select(
                    'sor.invoice_no',
                    'sor.customer_name',
                    'sor.customer_phone',
                    'sor.branch_name',
                    'sor.service_type',
                    DB::raw($this->sellTypeSqlCase('sor.service_type', 'label') . ' as service_type_label'),
                    'sor.total_amount',
                    'sor.created_at',
                    'u.username as staff_code',
                    DB::raw('COALESCE(u.name, sor.seller_name) as staff_name')
                )
                ->orderByDesc('sor.created_at')
                ->limit(20)
                ->get();

            return [
                'ok' => true,
                'message' => null,
                'metrics' => $metrics,
                'topHr' => $topHr,
                'topBranches' => $topBranches,
                'topSellTypes' => $topSellTypes,
                'recent' => $recent,
                'branches' => $branches,
                'sellTypes' => $sellTypes,
            ];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR Sell dashboard POS data: ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'metrics' => [
                    'pos_filtered_sales' => 0,
                    'pos_filtered_count' => 0,
                    'pos_average_sale' => 0,
                    'pos_branch_count' => 0,
                    'pos_seller_count' => 0,
                ],
                'topHr' => collect(),
                'topBranches' => collect(),
                'topSellTypes' => collect(),
                'recent' => collect(),
                'branches' => collect(),
                'sellTypes' => collect(),
            ];
        }
    }

    private function salesTrafficData(array $filters, string $period): array
    {
        try {
            $periodExpr = $period === 'monthly'
                ? "DATE_FORMAT(created_at, '%Y-%m')"
                : 'DATE(created_at)';
            $locationPeriodExpr = $period === 'monthly'
                ? "DATE_FORMAT(created_at, '%Y-%m')"
                : 'DATE(created_at)';
            $base = $this->filteredPosQuery($filters);
            $branches = $this->hrBranches();
            $sellTypes = $this->hrSellTypes();

            $metrics = [
                'sale_count' => (int) (clone $base)->count(),
                'sale_total' => (float) (clone $base)->sum('total_amount'),
                'location_count' => (int) (clone $base)->whereNotNull('branch_name')->where('branch_name', '!=', '')->distinct()->count('branch_name'),
                'average_sale' => 0,
            ];
            $metrics['average_sale'] = $metrics['sale_count'] > 0 ? $metrics['sale_total'] / $metrics['sale_count'] : 0;

            $trafficRows = (clone $base)
                ->selectRaw($periodExpr . ' as period_label')
                ->selectRaw('COUNT(*) as sale_count')
                ->selectRaw('COALESCE(SUM(total_amount), 0) as sale_total')
                ->groupBy(DB::raw($periodExpr))
                ->orderBy('period_label')
                ->get();

            $locationRows = (clone $base)
                ->selectRaw($locationPeriodExpr . ' as period_label')
                ->selectRaw("COALESCE(NULLIF(TRIM(branch_name), ''), 'Unknown') as branch_name")
                ->selectRaw('COUNT(*) as sale_count')
                ->selectRaw('COALESCE(SUM(total_amount), 0) as sale_total')
                ->groupBy(
                    DB::raw($locationPeriodExpr),
                    DB::raw("COALESCE(NULLIF(TRIM(branch_name), ''), 'Unknown')")
                )
                ->orderByDesc('period_label')
                ->orderByDesc('sale_total')
                ->get()
                ->groupBy('period_label')
                ->flatMap(function ($rows) {
                    return $rows->values()->map(function ($row, $index) {
                        $row->rank = $index + 1;

                        return $row;
                    });
                })
                ->values();

            $locationCards = $locationRows
                ->groupBy('branch_name')
                ->map(function ($rows, $branchName) {
                    return (object) [
                        'branch_name' => $branchName,
                        'sale_count' => $rows->sum('sale_count'),
                        'sale_total' => $rows->sum('sale_total'),
                        'average_sale' => (float) $rows->sum('sale_count') > 0 ? (float) $rows->sum('sale_total') / (float) $rows->sum('sale_count') : 0,
                        'rows' => $rows,
                    ];
                })
                ->sortByDesc('sale_total')
                ->values();

            return [
                'ok' => true,
                'message' => null,
                'metrics' => $metrics,
                'trafficRows' => $trafficRows,
                'locationCards' => $locationCards,
                'branches' => $branches,
                'sellTypes' => $sellTypes,
            ];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load HR Sales Traffic dashboard data: ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'metrics' => [
                    'sale_count' => 0,
                    'sale_total' => 0,
                    'location_count' => 0,
                    'average_sale' => 0,
                ],
                'trafficRows' => collect(),
                'locationCards' => collect(),
                'branches' => collect(),
                'sellTypes' => collect(),
            ];
        }
    }

    private function filteredPosQuery(array $filters, ?string $alias = null)
    {
        $table = $alias ? "sell_out_reports as {$alias}" : 'sell_out_reports';
        $prefix = $alias ? "{$alias}." : '';

        return DB::connection('hr')
            ->table($table)
            ->when(! empty($filters['start_date']), fn ($q) => $q->where($prefix . 'created_at', '>=', $filters['start_date'] . ' 00:00:00'))
            ->when(! empty($filters['end_date']), fn ($q) => $q->where($prefix . 'created_at', '<=', $filters['end_date'] . ' 23:59:59'))
            ->when(! empty($filters['branch_name']), fn ($q) => $q->whereRaw('TRIM(' . $prefix . 'branch_name) = ?', [$filters['branch_name']]))
            ->when(! empty($filters['sell_type']), function ($q) use ($filters, $prefix) {
                $q->whereIn($prefix . 'service_type', $this->sellTypeValues($filters['sell_type']));
            });
    }

    private function hrBranches()
    {
        return DB::connection('hr')
            ->table('sell_out_reports')
            ->selectRaw('DISTINCT TRIM(branch_name) as branch_name')
            ->whereNotNull('branch_name')
            ->where('branch_name', '!=', '')
            ->orderBy('branch_name')
            ->pluck('branch_name', 'branch_name');
    }

    private function hrSellTypes()
    {
        return DB::connection('hr')
            ->table('sell_out_reports')
            ->select('service_type')
            ->distinct()
            ->whereNotNull('service_type')
            ->where('service_type', '!=', '')
            ->orderBy('service_type')
            ->pluck('service_type')
            ->mapWithKeys(fn ($type) => [$this->normalizeSellTypeKey($type) => $this->sellTypeLabel($type)])
            ->unique();
    }

    private function sellTypeMap(): array
    {
        return [
            'sell' => ['label' => 'Sell / លក់', 'values' => ['sell', 'Sell', 'លក់', 'Sell / លក់', 'Sell/លក់']],
            'buy_in' => ['label' => 'Buy In / ទិញចូល', 'values' => ['buy in', 'Buy In', 'buy_in', 'buyin', 'ទិញចូល', 'Buy In / ទិញចូល', 'Buy In/ទិញចូល', 'ទិញចូល / Buy In', 'ទិញចូល/Buy In']],
            'repair' => ['label' => 'Repair / ជួសជុល', 'values' => ['repair', 'Repair', 'ជួសជុល', 'Repair / ជួសជុល', 'Repair/ជួសជុល', 'ជួសជុល / Repair', 'ជួសជុល/Repair']],
            'material' => ['label' => 'Material / សម្ភារ', 'values' => ['material', 'Material', 'materials', 'Materials', 'សម្ភារ', 'Material / សម្ភារ', 'Material/សម្ភារ', 'សម្ភារ / Material', 'សម្ភារ/Material']],
            'iron' => ['label' => 'Iron / អ៊ុត', 'values' => ['iron', 'Iron', 'អ៊ុត', 'Iron / អ៊ុត', 'Iron/អ៊ុត', 'អ៊ុត / Iron', 'អ៊ុត/Iron', 'Scots', 'scots']],
            'icloud_cus' => ['label' => 'iCloud Cus', 'values' => ['icloud cus', 'iCloud Cus', 'icloud_cus', 'icloudcus']],
        ];
    }

    private function normalizeSellTypeKey(?string $type): string
    {
        $normalized = mb_strtolower(trim((string) $type));

        foreach ($this->sellTypeMap() as $key => $config) {
            if (in_array($normalized, array_map(fn ($value) => mb_strtolower($value), $config['values']), true)) {
                return $key;
            }
        }

        return trim((string) $type);
    }

    private function sellTypeLabel(?string $type): string
    {
        $key = $this->normalizeSellTypeKey($type);
        $map = $this->sellTypeMap();

        return $map[$key]['label'] ?? ($type ?: '-');
    }

    private function sellTypeValues(?string $type): array
    {
        $key = $this->normalizeSellTypeKey($type);
        $map = $this->sellTypeMap();

        return $map[$key]['values'] ?? [$type];
    }

    private function sellTypeSqlCase(string $column, string $mode): string
    {
        $cases = collect($this->sellTypeMap())->map(function ($config, $key) use ($column, $mode) {
            $values = collect($config['values'])
                ->map(fn ($value) => "'" . str_replace("'", "''", mb_strtolower($value)) . "'")
                ->implode(', ');
            $result = $mode === 'key' ? $key : $config['label'];

            return "WHEN LOWER(TRIM({$column})) IN ({$values}) THEN '" . str_replace("'", "''", $result) . "'";
        })->implode(' ');

        return "CASE {$cases} ELSE COALESCE(NULLIF(TRIM({$column}), ''), 'Unknown') END";
    }

    private function managedDashboardData(int $businessId, string $today): array
    {
        $base = DB::table('hr_sell_records')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        return [
            'metrics' => [
                'managed_total_sales' => (float) (clone $base)->sum('sale_total'),
                'managed_count' => (int) (clone $base)->count(),
                'pending_approval' => (int) (clone $base)->where('approval_status', 'pending')->count(),
                'followups_due' => (int) (clone $base)->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', $today)->where('follow_up_status', '!=', 'completed')->count(),
                'commission_total' => (float) (clone $base)->where('approval_status', 'approved')->sum('commission_amount'),
                'due_total' => (float) (clone $base)->sum('due_total'),
            ],
        ];
    }

    private function canOpen(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.view') || $user->can('superadmin') || $user->can('business_settings.access');
    }
}
