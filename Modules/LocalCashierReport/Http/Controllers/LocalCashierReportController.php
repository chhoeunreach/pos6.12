<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\Exports\ArrayExport;
use App\Utils\Util;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class LocalCashierReportController extends Controller
{
    public function __construct(private Util $util)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        $businessId = (int) session('user.business_id');
        $permittedLocations = auth()->user()->permitted_locations($businessId);

        $users = DB::table('users')
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
        $locations = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->when($permittedLocations !== 'all', function ($query) use ($permittedLocations) {
                $query->whereIn('id', $permittedLocations);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('localcashierreport::index', [
            'users' => $users,
            'locations' => $locations,
            'paymentStatuses' => ['paid', 'due', 'partial', 'overdue'],
            'currencySymbol' => $this->currencySymbol(),
            'paymentMap' => config('localcashierreport.payment_method_map', []),
            'paymentLabels' => $this->paymentLabels($businessId),
            'khmerFontFamily' => config('localcashierreport.khmer_font_family'),
        ]);
    }

    public function datatable(Request $request)
    {
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        if (! $this->hasRequiredDateRange($request)) {
            return DataTables::of(collect())
                ->with('summary', $this->emptySummary())
                ->with('warning', 'Please select Date Range, then click Search.')
                ->make(true);
        }

        $lineQuery = $this->lineQuery($request);
        $summary = $this->buildSummary($request);

        return DataTables::of($lineQuery)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                $html = '<div class="btn-group">
                            <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle" data-toggle="dropdown" aria-expanded="false">'
                            . __('messages.actions') .
                            '<span class="caret"></span></button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view') || auth()->user()->can('view_own_sell_only')) {
                    $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                }
                if (auth()->user()->can('direct_sell.update')) {
                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->transaction_id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                }

                $html .= '</ul></div>';

                return $html;
            })
            ->editColumn('transaction_date', fn ($row) => Carbon::parse($row->transaction_date)->format('Y-m-d H:i'))
            ->editColumn('line_total', fn ($row) => (float) $row->unit_price * (float) $row->quantity)
            ->with('summary', $summary)
            ->rawColumns(['action'])
            ->make(true);
    }

    public function exportExcel(Request $request)
    {
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        if (! $this->hasRequiredDateRange($request)) {
            return back()->with('status', [
                'success' => 0,
                'msg' => 'Please select Date Range before export.',
            ]);
        }

        $rows = $this->getExportRows($request);
        $filename = 'local_cashier_report_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ArrayExport($rows), $filename);
    }

    public function exportPdf(Request $request)
    {
        abort_unless($request->user()->can('local_cashier_report.view'), 403);

        if (! $this->hasRequiredDateRange($request)) {
            return back()->with('status', [
                'success' => 0,
                'msg' => 'Please select Date Range before export.',
            ]);
        }

        $rows = $this->lineQuery($request)->get();
        $summary = $this->buildSummary($request);

        $pdf = Pdf::loadView('localcashierreport::partials.report_table', [
            'rows' => $rows,
            'summary' => $summary,
            'currencySymbol' => $this->currencySymbol(),
            'khmerFontFamily' => config('localcashierreport.khmer_font_family'),
            'isPdf' => true,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('local_cashier_report_' . now()->format('Ymd_His') . '.pdf');
    }

    private function lineQuery(Request $request)
    {
        $businessId = (int) session('user.business_id');
        $map = $this->paymentMap();

        $paymentSub = DB::table('transaction_payments as tp')
            ->select(
                'tp.transaction_id',
                DB::raw('SUM(tp.amount) as total_paid'),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['cash']}' THEN tp.amount ELSE 0 END) as cash"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['aba']}' THEN tp.amount ELSE 0 END) as aba"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['acleda']}' THEN tp.amount ELSE 0 END) as acleda"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['wing']}' THEN tp.amount ELSE 0 END) as wing"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['e_and_t']}' THEN tp.amount ELSE 0 END) as e_and_t"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['card']}' THEN tp.amount ELSE 0 END) as card"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['other']}' THEN tp.amount ELSE 0 END) as other")
            )
            ->groupBy('tp.transaction_id');

        $query = DB::table('transactions as t')
            ->join('transaction_sell_lines as tsl', 't.id', '=', 'tsl.transaction_id')
            ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->leftJoinSub($paymentSub, 'pay', function ($join) {
                $join->on('t.id', '=', 'pay.transaction_id');
            })
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->select([
                't.id as transaction_id',
                't.transaction_date',
                't.invoice_no',
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as cashier_name"),
                'bl.name as location_name',
                'v.sub_sku as sku',
                'p.name as product_name',
                'tsl.quantity as quantity',
                'tsl.unit_price_inc_tax as unit_price',
                DB::raw('(tsl.quantity * tsl.unit_price_inc_tax) as line_total'),
                't.discount_amount as discount',
                DB::raw('COALESCE(pay.total_paid, 0) as total_paid'),
                DB::raw('COALESCE(pay.cash, 0) as cash'),
                DB::raw('COALESCE(pay.aba, 0) as aba'),
                DB::raw('COALESCE(pay.acleda, 0) as acleda'),
                DB::raw('COALESCE(pay.wing, 0) as wing'),
                DB::raw('COALESCE(pay.e_and_t, 0) as e_and_t'),
                DB::raw('COALESCE(pay.card, 0) as card'),
                DB::raw('COALESCE(pay.other, 0) as other'),
                't.final_total as final_total',
                DB::raw('(t.final_total - COALESCE(pay.total_paid, 0)) as due'),
            ]);

        $this->applyFilters($query, $request, 't');

        return $query->orderByDesc('t.transaction_date');
    }

    private function buildSummary(Request $request): array
    {
        $businessId = (int) session('user.business_id');
        $map = $this->paymentMap();

        $paymentAgg = DB::table('transaction_payments as tp')
            ->select(
                'tp.transaction_id',
                DB::raw('SUM(tp.amount) as total_paid'),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['cash']}' THEN tp.amount ELSE 0 END) as cash"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['aba']}' THEN tp.amount ELSE 0 END) as aba"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['acleda']}' THEN tp.amount ELSE 0 END) as acleda"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['wing']}' THEN tp.amount ELSE 0 END) as wing"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['e_and_t']}' THEN tp.amount ELSE 0 END) as e_and_t"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['card']}' THEN tp.amount ELSE 0 END) as card"),
                DB::raw("SUM(CASE WHEN tp.method = '{$map['other']}' THEN tp.amount ELSE 0 END) as other")
            )
            ->groupBy('tp.transaction_id');

        $trx = DB::table('transactions as t')
            ->leftJoinSub($paymentAgg, 'pay', function ($join) {
                $join->on('t.id', '=', 'pay.transaction_id');
            })
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw('COUNT(t.id) as total_transactions')
            ->selectRaw('SUM(t.final_total) as total_sale')
            ->selectRaw('SUM(COALESCE(pay.total_paid,0)) as total_paid')
            ->selectRaw('SUM(COALESCE(pay.cash,0)) as total_cash')
            ->selectRaw('SUM(COALESCE(pay.aba,0)) as total_aba')
            ->selectRaw('SUM(COALESCE(pay.acleda,0)) as total_acleda')
            ->selectRaw('SUM(COALESCE(pay.wing,0)) as total_wing')
            ->selectRaw('SUM(COALESCE(pay.e_and_t,0)) as total_e_and_t')
            ->selectRaw('SUM(COALESCE(pay.card,0)) as total_card')
            ->selectRaw('SUM(COALESCE(pay.other,0)) as total_other')
            ->selectRaw('SUM(t.discount_amount) as total_discount');

        $this->applyFilters($trx, $request, 't');
        $trxSummary = (array) $trx->first();

        $qty = DB::table('transactions as t')
            ->join('transaction_sell_lines as tsl', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw('SUM(tsl.quantity) as total_qty');
        $this->applyFilters($qty, $request, 't');
        $qtySummary = (array) $qty->first();

        $userGroup = DB::table('transactions as t')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as label")
            ->selectRaw('SUM(t.final_total) as amount')
            ->groupBy('t.created_by', 'u.first_name', 'u.last_name');
        $this->applyFilters($userGroup, $request, 't');

        $locationGroup = DB::table('transactions as t')
            ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw('COALESCE(bl.name, "N/A") as label')
            ->selectRaw('SUM(t.final_total) as amount')
            ->groupBy('t.location_id', 'bl.name');
        $this->applyFilters($locationGroup, $request, 't');

        $payMethodGroup = DB::table('transactions as t')
            ->join('transaction_payments as tp', 't.id', '=', 'tp.transaction_id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->selectRaw('tp.method as label, SUM(tp.amount) as amount')
            ->groupBy('tp.method');
        $this->applyFilters($payMethodGroup, $request, 't');

        $totalSale = (float) ($trxSummary['total_sale'] ?? 0);
        $totalPaid = (float) ($trxSummary['total_paid'] ?? 0);

        return [
            'cards' => [
                'total_sale' => $totalSale,
                'total_paid' => $totalPaid,
                'total_cash' => (float) ($trxSummary['total_cash'] ?? 0),
                'total_aba' => (float) ($trxSummary['total_aba'] ?? 0),
                'total_acleda' => (float) ($trxSummary['total_acleda'] ?? 0),
                'total_wing' => (float) ($trxSummary['total_wing'] ?? 0),
                'total_e_and_t' => (float) ($trxSummary['total_e_and_t'] ?? 0),
                'total_card' => (float) ($trxSummary['total_card'] ?? 0),
                'total_other' => (float) ($trxSummary['total_other'] ?? 0),
                'total_due' => $totalSale - $totalPaid,
                'total_discount' => (float) ($trxSummary['total_discount'] ?? 0),
                'total_qty' => (float) ($qtySummary['total_qty'] ?? 0),
            ],
            'group_by_user' => $userGroup->get(),
            'group_by_location' => $locationGroup->get(),
            'group_by_payment_method' => $payMethodGroup->get(),
        ];
    }

    private function applyFilters($query, Request $request, string $prefix = 't'): void
    {
        $businessId = (int) session('user.business_id');
        $permittedLocations = auth()->user()->permitted_locations($businessId);

        if ($permittedLocations !== 'all') {
            $query->whereIn($prefix . '.location_id', $permittedLocations);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (! empty($startDate)) {
            $query->whereDate($prefix . '.transaction_date', '>=', Carbon::parse($startDate)->format('Y-m-d'));
        }
        if (! empty($endDate)) {
            $query->whereDate($prefix . '.transaction_date', '<=', Carbon::parse($endDate)->format('Y-m-d'));
        }

        if ($request->filled('user_ids')) {
            $ids = array_filter((array) $request->input('user_ids'));
            if (! empty($ids)) {
                $query->whereIn($prefix . '.created_by', $ids);
            }
        }

        if ($request->filled('location_ids')) {
            $ids = array_filter((array) $request->input('location_ids'));
            if (! empty($ids)) {
                $query->whereIn($prefix . '.location_id', $ids);
            }
        }

        if ($request->filled('payment_status')) {
            $query->where($prefix . '.payment_status', $request->input('payment_status'));
        }
    }

    private function paymentMap(): array
    {
        return array_merge([
            'cash' => 'cash',
            'aba' => 'custom_pay_1',
            'acleda' => 'custom_pay_2',
            'wing' => 'custom_pay_3',
            'e_and_t' => 'custom_pay_4',
            'card' => 'card',
            'other' => 'others',
        ], config('localcashierreport.payment_method_map', []));
    }

    private function getExportRows(Request $request): array
    {
        $symbol = $this->currencySymbol();
        $data = $this->lineQuery($request)->get();

        return $data->map(function ($r, $index) use ($symbol) {
            return [
                'No' => $index + 1,
                'Date' => Carbon::parse($r->transaction_date)->format('Y-m-d H:i'),
                'Invoice No' => $r->invoice_no,
                'Cashier/User' => $r->cashier_name,
                'Location' => $r->location_name,
                'SKU' => $r->sku,
                'Product Name' => $r->product_name,
                'Quantity' => (float) $r->quantity,
                'Unit Price' => $symbol . number_format((float) $r->unit_price, 2),
                'Line Total' => $symbol . number_format((float) $r->line_total, 2),
                'Discount' => $symbol . number_format((float) $r->discount, 2),
                'Total Paid' => $symbol . number_format((float) $r->total_paid, 2),
                'Cash' => $symbol . number_format((float) $r->cash, 2),
                'ABA' => $symbol . number_format((float) $r->aba, 2),
                'ACLEDA' => $symbol . number_format((float) $r->acleda, 2),
                'WING' => $symbol . number_format((float) $r->wing, 2),
                'E&T' => $symbol . number_format((float) $r->e_and_t, 2),
                'Card' => $symbol . number_format((float) $r->card, 2),
                'Other Payment' => $symbol . number_format((float) $r->other, 2),
                'Due' => $symbol . number_format((float) $r->due, 2),
            ];
        })->all();
    }

    private function currencySymbol(): string
    {
        return (string) data_get(session('currency'), 'symbol', '$');
    }

    private function hasRequiredDateRange(Request $request): bool
    {
        return $request->filled('start_date') && $request->filled('end_date');
    }

    private function emptySummary(): array
    {
        return [
            'cards' => [
                'total_sale' => 0,
                'total_paid' => 0,
                'total_cash' => 0,
                'total_aba' => 0,
                'total_acleda' => 0,
                'total_wing' => 0,
                'total_e_and_t' => 0,
                'total_card' => 0,
                'total_other' => 0,
                'total_due' => 0,
                'total_discount' => 0,
                'total_qty' => 0,
            ],
            'group_by_user' => [],
            'group_by_location' => [],
            'group_by_payment_method' => [],
        ];
    }

    private function paymentLabels(int $businessId): array
    {
        $map = $this->paymentMap();
        $paymentTypes = $this->util->payment_types(null, false, $businessId);

        return [
            'cash' => $paymentTypes[$map['cash']] ?? __('lang_v1.cash'),
            'aba' => $paymentTypes[$map['aba']] ?? strtoupper((string) $map['aba']),
            'acleda' => $paymentTypes[$map['acleda']] ?? strtoupper((string) $map['acleda']),
            'wing' => $paymentTypes[$map['wing']] ?? strtoupper((string) $map['wing']),
            'e_and_t' => $paymentTypes[$map['e_and_t']] ?? strtoupper((string) $map['e_and_t']),
            'card' => $paymentTypes[$map['card']] ?? __('lang_v1.card'),
            'other' => $paymentTypes[$map['other']] ?? __('lang_v1.other'),
        ];
    }
}
