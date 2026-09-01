<?php

namespace Modules\HrSellManagement\Http\Controllers;

use App\Transaction;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\HrSellManagement\Entities\HrSellRecord;
use Modules\HrSellManagement\Services\HrSellService;

class HrSellController extends Controller
{
    public function __construct(private HrSellService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless($this->canOpen(), 403);
        [$posHrSales, $hrBranches, $hrSellTypes, $hrSellers] = $this->posHrSellListData($request);

        return view('hrsellmanagement::sales.index', compact('posHrSales', 'hrBranches', 'hrSellTypes', 'hrSellers'));
    }

    public function link(Request $request)
    {
        abort_unless(auth()->user()->can('hr_sell.create'), 403);
        $data = $request->validate([
            'transaction_id' => 'required|integer',
            'hr_user_id' => 'required|integer',
            'supervisor_id' => 'nullable|integer',
            'commission_type' => 'nullable|string|in:percent,fixed',
            'commission_value' => 'nullable|numeric|min:0',
            'follow_up_date' => 'nullable|date',
            'internal_note' => 'nullable|string|max:2000',
        ]);

        $record = $this->service->linkTransaction((int) session('user.business_id'), (int) $data['transaction_id'], (int) $data['hr_user_id'], (int) auth()->id(), $data, $request);

        return redirect()->route('hr-sell.sales.show', $record->id)->with('status', ['success' => 1, 'msg' => 'Sale linked to HR']);
    }

    public function show(HrSellRecord $hrSell)
    {
        abort_unless($this->canOpen(), 403);
        $this->authorizeBusiness($hrSell);
        $hrSell->load(['transaction.contact', 'transaction.sell_lines.product', 'hrUser', 'notes', 'approvals']);
        $users = User::forDropdown((int) session('user.business_id'), false, false, true);
        $logs = DB::table('hr_sell_logs')->where('business_id', $hrSell->business_id)->where('hr_sell_record_id', $hrSell->id)->latest()->limit(100)->get();

        return view('hrsellmanagement::sales.show', compact('hrSell', 'users', 'logs'));
    }

    public function update(Request $request, HrSellRecord $hrSell)
    {
        abort_unless(auth()->user()->can('hr_sell.update'), 403);
        $this->authorizeBusiness($hrSell);
        $data = $request->validate([
            'hr_user_id' => 'nullable|integer',
            'supervisor_id' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,active,on_hold,completed,cancelled',
            'commission_type' => 'nullable|string|in:percent,fixed',
            'commission_value' => 'nullable|numeric|min:0',
            'follow_up_date' => 'nullable|date',
            'follow_up_status' => 'nullable|string|in:none,scheduled,called,completed,missed',
            'internal_note' => 'nullable|string|max:2000',
        ]);
        $this->service->updateRecord($hrSell, $data, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'HR sale updated']);
    }

    public function approve(Request $request, HrSellRecord $hrSell)
    {
        abort_unless(auth()->user()->can('hr_sell.approve'), 403);
        $this->authorizeBusiness($hrSell);
        $data = $request->validate(['level' => 'required|string|max:40', 'status' => 'required|string|in:approved,rejected', 'note' => 'nullable|string|max:2000']);
        $this->service->approve($hrSell, $data['level'], $data['status'], $data['note'] ?? null, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'Approval updated']);
    }

    public function storeNote(Request $request, HrSellRecord $hrSell)
    {
        abort_unless(auth()->user()->can('hr_sell.update'), 403);
        $this->authorizeBusiness($hrSell);
        $data = $request->validate(['note_type' => 'required|string|in:note,call,visit,problem,promise', 'note' => 'required|string|max:3000', 'next_follow_up_date' => 'nullable|date']);
        $this->service->addNote($hrSell, $data, $request);

        return back()->with('status', ['success' => 1, 'msg' => 'Note saved']);
    }

    public function posDetail($report_id)
    {
        abort_unless($this->canOpen(), 403);

        try {
            $report = DB::connection('hr')
                ->table('sell_out_reports as sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->where('sor.id', $report_id)
                ->select(
                    'sor.*',
                    DB::raw('COALESCE(u.name, sor.seller_name) as staff_name'),
                    'u.username as staff_code',
                    'u.avatar as staff_avatar'
                )
                ->first();

            if (empty($report)) {
                abort(404);
            }

            $report->service_type_label = $this->sellTypeLabel($report->service_type);

            $report->lines = DB::connection('hr')
                ->table('sell_out_report_lines')
                ->where('sell_out_report_id', $report_id)
                ->orderBy('id')
                ->get();

            $report->photos = DB::connection('hr')
                ->table('sell_out_report_photos')
                ->where('sell_out_report_id', $report_id)
                ->orderBy('id')
                ->get();

            return view('hrsellmanagement::sales.partials.pos_detail_modal', compact('report'));
        } catch (\Throwable $e) {
            \Log::warning('HR Sell POS detail error: ' . $e->getMessage());
            abort(404);
        }
    }

    public function posPhoto($photo_id)
    {
        abort_unless($this->canOpen(), 403);

        try {
            $photo = DB::connection('hr')
                ->table('sell_out_report_photos')
                ->where('id', $photo_id)
                ->first();

            if (empty($photo)) {
                abort(404);
            }

            $relativePath = ltrim($photo->photo_path ?? '', '/\\');
            if ($relativePath === '' || strpos($relativePath, '..') !== false) {
                abort(404);
            }

            $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            $candidateRoots = array_filter([
                env('HR_SELL_OUT_PHOTO_PATH'),
                env('HR_STORAGE_PATH'),
                storage_path('app/public'),
                public_path('storage'),
            ]);

            foreach ($candidateRoots as $root) {
                $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), '\\/');
                $path = $root . DIRECTORY_SEPARATOR . $relativePath;

                if (is_file($path)) {
                    return response()->file($path, [
                        'Content-Type' => mime_content_type($path) ?: 'image/jpeg',
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            }

            if (! empty($photo->photo_url)) {
                return redirect()->away($photo->photo_url);
            }

            abort(404);
        } catch (\Throwable $e) {
            \Log::warning('HR Sell POS photo error: ' . $e->getMessage());
            abort(404);
        }
    }

    private function authorizeBusiness(HrSellRecord $record): void
    {
        abort_unless((int) $record->business_id === (int) session('user.business_id'), 403);
    }

    private function canOpen(): bool
    {
        $user = auth()->user();

        return $user->can('hr_sell.view') || $user->can('superadmin') || $user->can('business_settings.access');
    }

    private function posHrSellListData(Request $request): array
    {
        try {
            $branches = DB::connection('hr')
                ->table('sell_out_reports')
                ->selectRaw('DISTINCT TRIM(branch_name) as branch_name')
                ->whereNotNull('branch_name')
                ->where('branch_name', '!=', '')
                ->orderBy('branch_name')
                ->pluck('branch_name', 'branch_name');

            $sellTypes = DB::connection('hr')
                ->table('sell_out_reports')
                ->select('service_type')
                ->distinct()
                ->whereNotNull('service_type')
                ->where('service_type', '!=', '')
                ->orderBy('service_type')
                ->pluck('service_type')
                ->mapWithKeys(fn ($type) => [$this->normalizeSellTypeKey($type) => $this->sellTypeLabel($type)])
                ->unique();

            $sellers = DB::connection('hr')
                ->table('sell_out_reports as sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->selectRaw("COALESCE(NULLIF(TRIM(u.username), ''), CAST(sor.user_id AS CHAR), CONCAT('seller:', TRIM(sor.seller_name))) as seller_key")
                ->selectRaw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(sor.seller_name), ''), 'Unknown') as seller_name")
                ->selectRaw("NULLIF(TRIM(u.username), '') as username")
                ->where(function ($q) {
                    $q->whereNotNull('sor.user_id')
                        ->orWhere(function ($query) {
                            $query->whereNotNull('sor.seller_name')->where('sor.seller_name', '!=', '');
                        });
                })
                ->groupBy('seller_key', 'seller_name', 'username')
                ->orderBy('seller_name')
                ->get()
                ->mapWithKeys(function ($seller) {
                    $label = trim(($seller->username ? $seller->username . ' - ' : '') . $seller->seller_name);

                    return [$seller->seller_key => $label ?: 'Unknown'];
                });

            $query = DB::connection('hr')
                ->table('sell_out_reports as sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->select(
                    'sor.id',
                    'sor.invoice_no',
                    'sor.customer_phone',
                    'sor.customer_name',
                    'sor.seller_name',
                    'sor.branch_name',
                    'sor.total_amount',
                    'sor.created_at',
                    'sor.service_type',
                    'u.username as staff_code',
                    DB::raw('COALESCE(u.name, sor.seller_name) as staff_name')
                );

            $query
                ->when($request->filled('start_date'), fn ($q) => $q->where('sor.created_at', '>=', $request->input('start_date') . ' 00:00:00'))
                ->when($request->filled('end_date'), fn ($q) => $q->where('sor.created_at', '<=', $request->input('end_date') . ' 23:59:59'))
                ->when($request->filled('branch_name'), fn ($q) => $q->whereRaw('TRIM(sor.branch_name) = ?', [$request->input('branch_name')]))
                ->when($request->filled('sell_type'), function ($q) use ($request) {
                    $sellType = $request->input('sell_type');
                    $q->whereIn('sor.service_type', $this->sellTypeValues($sellType));
                })
                ->when($request->filled('seller_key'), function ($q) use ($request) {
                    $sellerKey = $request->input('seller_key');
                    if (str_starts_with($sellerKey, 'seller:')) {
                        $q->whereRaw('TRIM(sor.seller_name) = ?', [substr($sellerKey, 7)]);
                    } elseif (preg_match('/^[A-Za-z]+[A-Za-z0-9_-]*\d+[A-Za-z0-9_-]*$/', $sellerKey)) {
                        $q->whereRaw('TRIM(u.username) = ?', [$sellerKey]);
                    } else {
                        $q->where('sor.user_id', $sellerKey);
                    }
                })
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = '%' . $request->input('search') . '%';
                    $q->where(function ($query) use ($search) {
                        $query->where('sor.invoice_no', 'like', $search)
                            ->orWhere('sor.customer_phone', 'like', $search)
                            ->orWhere('sor.customer_name', 'like', $search)
                            ->orWhere('sor.seller_name', 'like', $search)
                            ->orWhere('u.username', 'like', $search)
                            ->orWhere('u.name', 'like', $search);
                    });
                });

            $perPage = $this->posHrPerPage($request);
            $query->orderByDesc('sor.created_at');

            if ($perPage === 'all') {
                $allRows = $query->get();
                $rows = new LengthAwarePaginator($allRows, $allRows->count(), max($allRows->count(), 1), 1, [
                    'path' => $request->url(),
                    'pageName' => 'pos_hr_page',
                    'query' => $request->query(),
                ]);
            } else {
                $rows = $query->paginate($perPage, ['*'], 'pos_hr_page')
                    ->appends($request->query());
            }

            $rows->getCollection()->transform(function ($row) {
                $row->service_type_label = $this->sellTypeLabel($row->service_type);

                return $row;
            });

            return [$rows, $branches, $sellTypes, $sellers];
        } catch (\Throwable $e) {
            \Log::warning('Unable to load POS HR sell list in HrSellManagement: ' . $e->getMessage());

            $emptyPerPage = $this->posHrPerPage($request);
            $emptyRows = new LengthAwarePaginator(
                [],
                0,
                $emptyPerPage === 'all' ? 1 : $emptyPerPage,
                (int) $request->input('pos_hr_page', 1),
                [
                    'path' => $request->url(),
                    'pageName' => 'pos_hr_page',
                    'query' => $request->query(),
                ]
            );

            return [$emptyRows, collect(), collect(), collect()];
        }
    }

    private function posHrPerPage(Request $request)
    {
        $perPage = (string) $request->input('pos_hr_per_page', '50');
        $allowed = ['25', '50', '100', '200', '500', 'all'];

        return in_array($perPage, $allowed, true) ? ($perPage === 'all' ? 'all' : (int) $perPage) : 50;
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

}
