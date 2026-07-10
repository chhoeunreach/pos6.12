<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\BusinessLocation;
use App\Utils\TransactionUtil;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HrSellListReportController extends Controller
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        if (! auth()->user()->can('stock_report.view') && ! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            $branch_name = $request->input('branch_name');
            $sell_type = $request->input('sell_type');
            $search = $request->input('search.value');

            try {
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
                        DB::raw('COALESCE(u.name, sor.seller_name) as staff_name'),
                        'u.employee_code as staff_code',
                        'u.avatar as staff_avatar'
                    );

                if (! empty($start_date) && ! empty($end_date)) {
                    $query->where('sor.created_at', '>=', $start_date . ' 00:00:00')
                        ->where('sor.created_at', '<=', $end_date . ' 23:59:59');
                }

                if (! empty($branch_name)) {
                    $query->whereRaw('TRIM(sor.branch_name) = ?', [$branch_name]);
                }

                if (! empty($sell_type)) {
                    if (in_array($sell_type, ['sell', 'លក់'])) {
                        $query->whereIn('sor.service_type', ['sell', 'លក់']);
                    } else {
                        $query->where('sor.service_type', $sell_type);
                    }
                }

                if (! empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('sor.invoice_no', 'like', "%{$search}%")
                            ->orWhere('sor.customer_phone', 'like', "%{$search}%")
                            ->orWhere('sor.customer_name', 'like', "%{$search}%")
                            ->orWhere('sor.seller_name', 'like', "%{$search}%")
                            ->orWhere('u.name', 'like', "%{$search}%")
                            ->orWhere('u.employee_code', 'like', "%{$search}%");
                    });
                }

                return Datatables::of($query)
                    ->addColumn('action', function ($row) {
                        return '<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal hr-sell-list-detail-btn" data-href="' . route('ssi.report.hr_sell_list_detail', [$row->id]) . '" data-container=".view_modal"><i class="fa fa-eye"></i> ' . __('messages.view') . '</button>';
                    })
                    ->editColumn('total_amount', function ($row) {
                        return '<span class="display_currency" data-currency_symbol="true" data-orig-value="' . $row->total_amount . '">' . $row->total_amount . '</span>';
                    })
                    ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                    ->editColumn('staff_name', function ($row) {
                        $avatar = ! empty($row->staff_avatar) ? ltrim($row->staff_avatar, '/') : null;
                        $avatarUrl = $avatar ? asset('uploads/avatar/' . rawurlencode($avatar)) : null;
                        $initial = strtoupper(mb_substr($row->staff_name ?: 'S', 0, 1));
                        $code = $row->staff_code ?: '';

                        $img = '';
                        if (! empty($avatarUrl)) {
                            $img = '<img src="' . $avatarUrl . '" alt="' . e($row->staff_name) . '" class="tw-w-5 tw-h-5 tw-rounded-full tw-object-cover tw-inline-block tw-mr-1" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'inline-flex\';">';
                        }
                        $fallback = '<span class="tw-w-5 tw-h-5 tw-rounded-full tw-bg-sky-100 tw-text-sky-700 tw-text-[10px] tw-font-bold tw-inline-flex tw-items-center tw-justify-center tw-mr-1" ' . (! empty($avatarUrl) ? 'style="display:none;"' : '') . '>' . $initial . '</span>';

                        return $img . $fallback . '<span>' . e($row->staff_name) . '</span>' . ($code ? ' <span class="tw-text-slate-400 tw-text-[10px]">(' . e($code) . ')</span>' : '');
                    })
                    ->filterColumn('staff_name', function ($query, $keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('u.name', 'like', "%{$keyword}%")
                                ->orWhere('sor.seller_name', 'like', "%{$keyword}%");
                        });
                    })
                    ->rawColumns(['action', 'total_amount', 'staff_name'])
                    ->make(true);
            } catch (\Exception $e) {
                \Log::warning('HR sell list report error: ' . $e->getMessage());

                return response()->json([
                    'draw' => (int) $request->input('draw', 0),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                ]);
            }
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);

        try {
            $sell_types = DB::connection('hr')
                ->table('sell_out_reports')
                ->select('service_type')
                ->distinct()
                ->whereNotNull('service_type')
                ->where('service_type', '!=', '')
                ->orderBy('service_type')
                ->get()
                ->pluck('service_type');
            $branches = DB::connection('hr')
                ->table('sell_out_reports')
                ->selectRaw('DISTINCT TRIM(branch_name) as branch_name')
                ->whereNotNull('branch_name')
                ->where('branch_name', '!=', '')
                ->orderBy('branch_name')
                ->get()
                ->pluck('branch_name', 'branch_name');
        } catch (\Exception $e) {
            \Log::warning('Unable to load HR filters: ' . $e->getMessage());
            $sell_types = collect();
            $branches = collect();
        }

        return view('smartstockinventory::report.hr_sell_list')
            ->with(compact('business_locations', 'sell_types', 'branches'));
    }

    public function getDetail($report_id)
    {
        try {
            $report = DB::connection('hr')
                ->table('sell_out_reports as sor')
                ->leftJoin('users as u', 'u.id', '=', 'sor.user_id')
                ->where('sor.id', $report_id)
                ->select(
                    'sor.*',
                    DB::raw('COALESCE(u.name, sor.seller_name) as staff_name'),
                    'u.employee_code as staff_code',
                    'u.avatar as staff_avatar'
                )
                ->first();

            if (empty($report)) {
                abort(404);
            }

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

            return view('smartstockinventory::report.partials.hr_sell_list_detail_modal')
                ->with(compact('report'));
        } catch (\Exception $e) {
            \Log::warning('HR sell list detail error: ' . $e->getMessage());
            abort(404);
        }
    }

    public function getPhoto($photo_id)
    {
        try {
            $photo = DB::connection('hr')
                ->table('sell_out_report_photos')
                ->where('id', $photo_id)
                ->first();

            if (empty($photo)) {
                abort(404);
            }

            $photoUrl = $photo->photo_url ?: rtrim(env('HR_APP_URL', config('app.url')), '/') . '/storage/' . ltrim($photo->photo_path, '/');

            $imageContent = @file_get_contents($photoUrl);

            if ($imageContent === false) {
                $imageContent = @file_get_contents(rtrim(env('HR_APP_URL', config('app.url')), '/') . '/storage/' . ltrim($photo->photo_path, '/'));
            }

            if ($imageContent === false) {
                abort(404);
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);

            return response($imageContent, 200)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            \Log::warning('HR photo fetch error: ' . $e->getMessage());
            abort(404);
        }
    }

    public function getServiceTypes(Request $request)
    {
        $branch_name = $request->input('branch_name');

        try {
            $query = DB::connection('hr')
                ->table('sell_out_reports')
                ->select('service_type')
                ->distinct()
                ->whereNotNull('service_type')
                ->where('service_type', '!=', '')
                ->orderBy('service_type');

            if (! empty($branch_name)) {
                $query->where('branch_name', $branch_name);
            }

            return response()->json($query->get()->pluck('service_type'));
        } catch (\Exception $e) {
            \Log::warning('Unable to load HR service types: ' . $e->getMessage());

            return response()->json([]);
        }
    }
}
