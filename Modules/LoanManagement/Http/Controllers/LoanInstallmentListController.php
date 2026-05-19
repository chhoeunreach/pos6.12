<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\Services\TelegramBotService;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class LoanInstallmentListController extends Controller
{
    protected function hasCol(string $col): bool
    {
        return Schema::connection('mysql_loan')->hasColumn('loans', $col);
    }

    protected function loanTableHasCol(string $table, string $col): bool
    {
        return Schema::connection('mysql_loan')->hasTable($table)
            && Schema::connection('mysql_loan')->hasColumn($table, $col);
    }

    protected function loanSafeColumns(string $table, array $payload): array
    {
        if (! Schema::connection('mysql_loan')->hasTable($table)) {
            return [];
        }

        return array_intersect_key($payload, array_flip(Schema::connection('mysql_loan')->getColumnListing($table)));
    }

    protected function assetFromPublicPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (Str::startsWith($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (preg_match('#^uploads/loan_location_assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (preg_match('#^loan_location_assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        if (preg_match('#^loan-management/location-assets/(\d+)/([^/]+)$#', $path, $matches)) {
            return $this->fileDataUri($this->moduleLocationAssetPath((int) $matches[1], $matches[2]));
        }

        if (Str::startsWith($path, 'storage/') && file_exists(storage_path('app/public/'.substr($path, 8)))) {
            return asset($path);
        }

        return null;
    }

    protected function moduleLocationAssetPath(int $location, string $filename): ?string
    {
        if (Str::contains($filename, ['/', '\\']) || $filename !== basename($filename)) {
            return null;
        }

        $path = base_path('Modules/LoanManagement/loan_location_assets/'.$location.'/'.$filename);

        return is_file($path) ? $path : null;
    }

    protected function fileDataUri(?string $path): ?string
    {
        if (empty($path) || ! is_file($path)) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($path) : null;
        if (empty($mime) || ! Str::startsWith($mime, 'image/')) {
            $mime = 'image/'.strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpeg');
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    protected function firstExistingPublicAsset(array $paths): ?string
    {
        foreach ($paths as $path) {
            $asset = $this->assetFromPublicPath($path);
            if (! empty($asset)) {
                return $asset;
            }
        }

        return null;
    }

    protected function businessLogoAsset(): ?string
    {
        $logoName = session('business.logo');
        if (empty($logoName) && Schema::hasTable('business')) {
            $businessId = session('business.id') ?: (auth()->user()->business_id ?? null);
            if (! empty($businessId) && Schema::hasColumn('business', 'logo')) {
                $logoName = DB::table('business')->where('id', $businessId)->value('logo');
            }
        }

        $paths = [];
        if (! empty($logoName)) {
            $paths[] = 'uploads/business_logos/'.$logoName;
            $paths[] = 'storage/business_logos/'.$logoName;
            $paths[] = 'business_logos/'.$logoName;
            $paths[] = $logoName;
        }

        return $this->firstExistingPublicAsset(array_merge($paths, [
            'uploads/logo.png',
            'img/logo.png',
            'logo.png',
        ]));
    }

    protected function paymentsForPrintSchedules($payments, $installments)
    {
        $assigned = $payments
            ->filter(fn ($payment) => ! empty($payment->schedule_id))
            ->map(function ($payment) {
                $payment->_print_schedule_id = $payment->schedule_id;
                $payment->_print_amount = (float) ($payment->total_paid_base ?? $payment->amount ?? 0);

                return $payment;
            });

        $unassigned = $payments
            ->filter(fn ($payment) => empty($payment->schedule_id))
            ->sortByDesc(fn ($payment) => $payment->paid_at ?? $payment->paid_date ?? $payment->id ?? 0)
            ->values();

        if ($unassigned->isEmpty()) {
            return $assigned->values();
        }

        $allocated = collect();
        $scheduleRemaining = $installments->mapWithKeys(function ($row) {
            $paid = (float) ($row->paid_value ?? 0);

            return [$row->id => max(0, $paid)];
        });

        foreach ($unassigned as $payment) {
            $remainingPayment = (float) ($payment->total_paid_base ?? $payment->amount ?? 0);
            if ($remainingPayment <= 0) {
                continue;
            }

            foreach ($installments as $row) {
                $remainingSchedule = (float) ($scheduleRemaining[$row->id] ?? 0);
                if ($remainingSchedule <= 0) {
                    continue;
                }

                $amount = min($remainingPayment, $remainingSchedule);
                $line = clone $payment;
                $line->_print_schedule_id = $row->id;
                $line->_print_amount = $amount;
                $allocated->push($line);

                $remainingPayment -= $amount;
                $scheduleRemaining[$row->id] = $remainingSchedule - $amount;

                if ($remainingPayment <= 0) {
                    break;
                }
            }

            if ($remainingPayment > 0) {
                $line = clone $payment;
                $line->_print_schedule_id = null;
                $line->_print_amount = $remainingPayment;
                $allocated->push($line);
            }
        }

        return $assigned->concat($allocated)->values();
    }

    protected function expandPaymentsWithDetailsForPrint($payments)
    {
        $paymentIds = $payments->pluck('id')->filter()->unique()->values();
        if ($paymentIds->isEmpty() || ! Schema::connection('mysql_loan')->hasTable('loan_payment_details')) {
            return $payments;
        }

        $detailColumns = Schema::connection('mysql_loan')->getColumnListing('loan_payment_details');
        $selectColumns = array_values(array_intersect([
            'id',
            'payment_id',
            'payment_method_snapshot',
            'method',
            'amount_base',
            'amount',
        ], $detailColumns));

        if (! in_array('payment_id', $selectColumns, true)) {
            return $payments;
        }

        $detailsByPayment = DB::connection('mysql_loan')
            ->table('loan_payment_details')
            ->select($selectColumns)
            ->whereIn('payment_id', $paymentIds)
            ->get()
            ->groupBy('payment_id');

        if ($detailsByPayment->isEmpty()) {
            return $payments;
        }

        return $payments->flatMap(function ($payment) use ($detailsByPayment) {
            $details = $detailsByPayment->get($payment->id, collect());
            if ($details->isEmpty()) {
                return [$payment];
            }

            return $details->map(function ($detail) use ($payment) {
                $line = clone $payment;
                $method = trim((string) ($detail->payment_method_snapshot ?? $detail->method ?? ''));

                if ($method !== '' && strtolower($method) !== 'unknown') {
                    $line->payment_method_snapshot = $method;
                    $line->channel = $method;
                    $line->method = $detail->method ?? $method;
                }

                $amount = (float) ($detail->amount_base ?? $detail->amount ?? 0);
                if ($amount > 0) {
                    $line->total_paid_base = $amount;
                    $line->amount = $amount;
                }

                return $line;
            });
        })->values();
    }

    protected function coreLocationNames($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty() || ! Schema::hasTable('business_locations')) {
            return [];
        }

        return DB::table('business_locations')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->all();
    }

    protected function loanLocationNames($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty() || ! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return [];
        }

        return DB::connection('mysql_loan')->table('loan_business_locations')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->all();
    }

    protected function coreLocationIdsByName(string $name): array
    {
        if (! Schema::hasTable('business_locations')) {
            return [];
        }

        return DB::table('business_locations')
            ->where('name', $name)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function loanLocationIdsByName(string $name): array
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return [];
        }

        return DB::connection('mysql_loan')->table('loan_business_locations')
            ->where('name', $name)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resolveLocationDisplay($row): string
    {
        $snapshot = trim((string) ($row->location_name_snapshot ?? ''));
        if ($snapshot !== '' && ! preg_match('/^Location #\d+$/', $snapshot)) {
            return $snapshot;
        }

        $loanLocationId = $row->business_location_id ?? null;
        $loanNames = $this->loanLocationNames([$loanLocationId]);
        if (! empty($loanNames[$loanLocationId])) {
            return $loanNames[$loanLocationId];
        }

        $mainLocationId = $row->main_location_id ?? null;
        $coreNames = $this->coreLocationNames([$mainLocationId]);
        if (! empty($coreNames[$mainLocationId])) {
            return $coreNames[$mainLocationId];
        }

        return $loanLocationId ? 'Location #'.$loanLocationId : '-';
    }

    protected function coreUserNames($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty() || ! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $ids)
            ->selectRaw("id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))), ''), username) as display_name")
            ->pluck('display_name', 'id')
            ->all();
    }

    public function index()
    {
        $locations = [];
        $collectors = [];

        if (Schema::connection('mysql_loan')->hasTable('loans')) {
            $collectorIdCol = $this->hasCol('collector_id')
                ? 'collector_id'
                : ($this->hasCol('assigned_to') ? 'assigned_to' : null);

            if ($this->hasCol('location_name_snapshot')) {
                $locations = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('location_name_snapshot')
                    ->where('location_name_snapshot', '!=', '')
                    ->distinct()
                    ->orderBy('location_name_snapshot')
                    ->pluck('location_name_snapshot', 'location_name_snapshot')
                    ->all();
            }
            if ($this->hasCol('business_location_id')) {
                $loanLocationIds = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('business_location_id')
                    ->distinct()
                    ->orderBy('business_location_id')
                    ->pluck('business_location_id');
                foreach ($this->loanLocationNames($loanLocationIds) as $id => $name) {
                    $locations[$name] = $name;
                }
            }
            if ($this->hasCol('main_location_id')) {
                $mainLocationIds = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('main_location_id')
                    ->distinct()
                    ->orderBy('main_location_id')
                    ->pluck('main_location_id');
                foreach ($this->coreLocationNames($mainLocationIds) as $id => $name) {
                    $locations[$name] = $name;
                }
            }

            if ($this->hasCol('collector_name_snapshot')) {
                $collectors = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull('collector_name_snapshot')
                    ->where('collector_name_snapshot', '!=', '')
                    ->distinct()
                    ->orderBy('collector_name_snapshot')
                    ->pluck('collector_name_snapshot', 'collector_name_snapshot')
                    ->all();
            }
            if ($collectorIdCol) {
                $collectorIds = DB::connection('mysql_loan')->table('loans')
                    ->whereNotNull($collectorIdCol)
                    ->distinct()
                    ->orderBy($collectorIdCol)
                    ->pluck($collectorIdCol);
                foreach ($this->coreUserNames($collectorIds) as $id => $name) {
                    $collectors[$id] = $name;
                }
            }
        }

        return view('loanmanagement::loans.index', compact('locations', 'collectors'));
    }

    public function data(Request $request)
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')) {
            return DataTables::of(collect([]))->make(true);
        }

        $q = DB::connection('mysql_loan')->table('loans as l')
            ->selectRaw(
                'l.id, '.
                ($this->hasCol('loan_number') ? 'l.loan_number' : 'CAST(l.id as CHAR)').' as loan_number, '.
                ($this->hasCol('loan_date') ? 'l.loan_date' : 'l.created_at').' as loan_date, '.
                ($this->hasCol('customer_name_snapshot') ? 'l.customer_name_snapshot' : 'NULL').' as customer_name_snapshot, '.
                ($this->hasCol('customer_phone_snapshot') ? 'l.customer_phone_snapshot' : 'NULL').' as customer_phone_snapshot, '.
                ($this->hasCol('main_location_id') ? 'l.main_location_id' : 'NULL').' as main_location_id, '.
                ($this->hasCol('business_location_id') ? 'l.business_location_id' : 'NULL').' as business_location_id, '.
                ($this->hasCol('location_name_snapshot') ? 'l.location_name_snapshot' : ($this->hasCol('business_location_id') ? "CONCAT('Location #', l.business_location_id)" : 'NULL')).' as location_name_snapshot, '.
                ($this->hasCol('principal_amount') ? 'l.principal_amount' : '0').' as principal_amount, '.
                ($this->hasCol('paid_amount') ? 'l.paid_amount' : '0').' as paid_amount, '.
                ($this->hasCol('balance_amount') ? 'l.balance_amount' : '0').' as balance_amount, '.
                ($this->hasCol('status') ? 'l.status' : "'pending'").' as status, '.
                ($this->hasCol('currency') ? 'l.currency' : "'USD'").' as currency, '.
                ($this->hasCol('source_invoice_no') ? 'l.source_invoice_no' : 'NULL').' as source_invoice_no, '.
                ($this->hasCol('collector_id') ? 'l.collector_id' : 'NULL').' as collector_id, '.
                ($this->hasCol('assigned_to') ? 'l.assigned_to' : 'NULL').' as assigned_to, '.
                ($this->hasCol('collector_name_snapshot') ? 'l.collector_name_snapshot' : ($this->hasCol('collector_id') ? "CONCAT('Collector #', l.collector_id)" : 'NULL')).' as collector_name_snapshot'
            );

        if ($request->filled('start_date')) $q->whereDate('l.loan_date', '>=', $request->start_date);
        if ($request->filled('end_date')) $q->whereDate('l.loan_date', '<=', $request->end_date);
        if ($request->filled('status') && $this->hasCol('status')) $q->where('l.status', $request->status);
        if ($request->filled('location_name')) {
            $locationFilter = (string) $request->location_name;
            $q->where(function ($query) use ($locationFilter) {
                if ($this->hasCol('location_name_snapshot')) {
                    $query->orWhere('l.location_name_snapshot', $locationFilter);
                }
                $loanLocationIds = $this->loanLocationIdsByName($locationFilter);
                if (! empty($loanLocationIds) && $this->hasCol('business_location_id')) {
                    $query->orWhereIn('l.business_location_id', $loanLocationIds);
                }
                $coreLocationIds = $this->coreLocationIdsByName($locationFilter);
                if (! empty($coreLocationIds) && $this->hasCol('main_location_id')) {
                    $query->orWhereIn('l.main_location_id', $coreLocationIds);
                }
                if (is_numeric($locationFilter)) {
                    if ($this->hasCol('main_location_id')) $query->orWhere('l.main_location_id', (int) $locationFilter);
                    if ($this->hasCol('business_location_id')) $query->orWhere('l.business_location_id', (int) $locationFilter);
                }
            });
        }
        if ($request->filled('collector_name')) {
            $collectorFilter = (string) $request->collector_name;
            $q->where(function ($query) use ($collectorFilter) {
                if ($this->hasCol('collector_name_snapshot')) {
                    $query->orWhere('l.collector_name_snapshot', $collectorFilter);
                }
                if (is_numeric($collectorFilter)) {
                    if ($this->hasCol('collector_id')) $query->orWhere('l.collector_id', (int) $collectorFilter);
                    if ($this->hasCol('assigned_to')) $query->orWhere('l.assigned_to', (int) $collectorFilter);
                }
            });
        }
        if ($request->filled('customer') && $this->hasCol('customer_name_snapshot')) $q->where('l.customer_name_snapshot', 'like', '%'.$request->customer.'%');

        return DataTables::of($q)
            ->editColumn('principal_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->principal_amount.'</span>')
            ->editColumn('paid_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->paid_amount.'</span>')
            ->editColumn('balance_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->balance_amount.'</span>')
            ->editColumn('location_name_snapshot', function ($r) {
                return e($this->resolveLocationDisplay($r));
            })
            ->editColumn('collector_name_snapshot', function ($r) {
                $snapshot = trim((string) ($r->collector_name_snapshot ?? ''));
                if ($snapshot !== '' && ! preg_match('/^Collector #\d+$/', $snapshot)) {
                    return e($snapshot);
                }

                $id = $r->collector_id ?? $r->assigned_to ?? null;
                $names = $this->coreUserNames([$id]);

                return e($names[$id] ?? ($id ? 'Collector #'.$id : '-'));
            })
            ->editColumn('status', function ($r) {
                $map = ['draft' => 'default', 'pending' => 'warning', 'approved' => 'info', 'active' => 'primary', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'default', 'defaulted' => 'danger'];
                $c = $map[$r->status] ?? 'default';
                return '<span class="label label-'.$c.'">'.ucfirst($r->status).'</span>';
            })
            ->addColumn('action', function ($r) {
                $user = auth()->user();
                $view = '<a href="'.route('loan-management.loans.view', $r->id).'" class="btn btn-xs btn-info">View</a>';
                $print = ' <button type="button" data-href="'.route('loan-management.loans.print-modal', $r->id).'" data-container=".view_modal" class="btn btn-xs btn-default btn-modal"><i class="fa fa-print"></i> Print Loan</button>';
                $edit = ($user && $user->can('loan_management.edit') && in_array(strtolower((string) $r->status), ['draft', 'pending']))
                    ? ' <a href="'.route('loan-management.loans.edit', $r->id).'" class="btn btn-xs btn-primary">Edit</a>'
                    : '';
                $delete = ($user && $user->can('loan_management.delete') && in_array(strtolower((string) $r->status), ['draft', 'pending']))
                    ? ' <button type="button" class="btn btn-xs btn-danger btn-delete-loan" data-url="'.route('loan-management.loans.destroy', $r->id).'">Delete</button>'
                    : '';

                $statusBtn = '';
                if ($user && $user->can('loan_management.approve')) {
                    $statusBtn = ' <div class="btn-group">
                        <button type="button" class="btn btn-xs btn-warning dropdown-toggle" data-toggle="dropdown">Status <span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-right">
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="pending">Pending</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="approved">Approved</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="active">Active</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="completed">Completed</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="rejected">Rejected</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="cancelled">Cancelled</a></li>
                            <li><a href="#" class="btn-change-status" data-url="'.route('loan-management.loans.status', $r->id).'" data-status="defaulted">Defaulted</a></li>
                        </ul>
                    </div>';
                }

                return $view.$print.$edit.$delete.$statusBtn;
            })
            ->rawColumns(['status', 'principal_amount', 'paid_amount', 'balance_amount', 'action'])
            ->make(true);
    }

    public function printModal(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $printUrl = route('loan-management.loans.print', $loan);
        $autoPrintUrl = route('loan-management.loans.print', ['loan' => $loan, 'auto_print' => 1]);

        return view('loanmanagement::loans.print.modal', compact('loanRow', 'printUrl', 'autoPrintUrl'));
    }

    public function print(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $customerRow = null;
        if (Schema::connection('mysql_loan')->hasTable('loan_customers') && ! empty($loanRow->customer_id)) {
            $customerRow = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanRow->customer_id)->first();
        }

        $contact = null;
        if (! empty($loanRow->main_contact_id) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')->where('id', $loanRow->main_contact_id)->first();
        }

        $customer = (object) [
            'name' => $loanRow->customer_name_snapshot
                ?? ($customerRow->name ?? ($customerRow->customer_name ?? ($contact->name ?? '-'))),
            'mobile' => $loanRow->customer_phone_snapshot
                ?? ($customerRow->phone ?? ($customerRow->mobile ?? ($customerRow->login_phone ?? ($contact->mobile ?? '-')))),
            'address_line_1' => $loanRow->customer_address_snapshot
                ?? ($customerRow->address ?? ($contact->address_line_1 ?? '-')),
            'custom_field1' => $customerRow->id_card_number ?? ($contact->custom_field1 ?? '-'),
            'co_borrower' => $customerRow->spouse_name ?? ($customerRow->family_contact_name ?? '-'),
            'co_borrower_phone' => $customerRow->spouse_phone ?? ($customerRow->family_contact_phone ?? '-'),
        ];

        $locationRow = null;
        if (Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            if (! empty($loanRow->business_location_id)) {
                $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')
                    ->where('id', $loanRow->business_location_id)
                    ->orWhere('main_location_id', $loanRow->business_location_id)
                    ->first();
            }
            if (! $locationRow && ! empty($loanRow->main_location_id)) {
                $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')->where('main_location_id', $loanRow->main_location_id)->first();
            }
            if (! $locationRow && ! empty($loanRow->location_name_snapshot)) {
                $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')->where('name', $loanRow->location_name_snapshot)->first();
            }
        }

        $locationName = $loanRow->location_name_snapshot ?? ($locationRow->name ?? null);
        if (empty($locationName)) {
            $locationId = $loanRow->main_location_id ?? $loanRow->business_location_id ?? null;
            if ($locationId && Schema::hasTable('business_locations')) {
                $locationName = DB::table('business_locations')->where('id', $locationId)->value('name');
            }
        }

        $products = collect();
        if (Schema::connection('mysql_loan')->hasTable('loan_items')) {
            $products = DB::connection('mysql_loan')->table('loan_items')
                ->where('loan_id', $loan)
                ->get()
                ->map(function ($item) {
                    $qty = $item->qty ?? $item->quantity ?? 1;
                    $unitPrice = $item->unit_price ?? $item->unit_price_inc_tax ?? 0;

                    return (object) [
                        'product_sku' => $item->sku_snapshot ?? $item->product_sku ?? '-',
                        'product_name' => $item->product_name_snapshot ?? $item->product_name ?? '-',
                        'quantity' => $qty,
                        'unit_price_inc_tax' => $unitPrice,
                        'subtotal' => $item->line_total ?? $item->total_price ?? ((float) $qty * (float) $unitPrice),
                        'imei' => $item->imei_snapshot ?? '-',
                        'serial' => $item->serial_number_snapshot ?? '-',
                        'lot' => $item->lot_number_snapshot ?? '-',
                    ];
                });
        }

        $installments = collect();
        if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            $installments = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'installment_no') ? 'installment_no' : 'due_date')
                ->get()
                ->map(function ($row, $index) {
                    $principal = (float) ($row->principal_amount ?? 0);
                    if ($principal <= 0) {
                        $principal = (float) ($row->principal_due ?? 0);
                    }
                    $interest = (float) ($row->interest_amount ?? 0);
                    if ($interest <= 0) {
                        $interest = (float) ($row->interest_due ?? 0);
                    }
                    $amountDue = (float) ($row->schedule_amount ?? 0);
                    if ($amountDue <= 0) {
                        $amountDue = (float) ($row->amount_due ?? 0);
                    }
                    if ($amountDue <= 0) {
                        $amountDue = round($principal + $interest, 2);
                    }
                    $paidAmount = (float) ($row->paid_amount ?? 0);
                    if ($paidAmount <= 0) {
                        $paidAmount = (float) ($row->amount_paid ?? 0);
                    }
                    $balance = (float) ($row->balance_amount ?? 0);
                    if ($balance <= 0) {
                        $balance = (float) ($row->amount_balance ?? 0);
                    }
                    if ($balance <= 0 && $amountDue > $paidAmount) {
                        $balance = max(0, $amountDue - $paidAmount);
                    }

                    return (object) [
                        'id' => $row->id ?? null,
                        'installment_number' => $row->installment_no ?? ($index + 1),
                        'installmentdate' => $row->due_date ?? null,
                        'installment_value' => $principal,
                        'benefit_value' => $interest,
                        'amount_due' => $amountDue,
                        'paid_value' => $paidAmount,
                        'balance_amount' => $balance,
                        'paid_at' => $row->paid_at ?? null,
                        'status' => $row->status ?? '-',
                    ];
                });
        }

        $payments = collect();
        if (Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            $payments = DB::connection('mysql_loan')->table('loan_payments')
                ->where('loan_id', $loan)
                ->orderByDesc($this->loanTableHasCol('loan_payments', 'paid_date') ? 'paid_date' : 'paid_at')
                ->get();
        }
        $payments = $this->expandPaymentsWithDetailsForPrint($payments);
        $payments = $this->paymentsForPrintSchedules($payments, $installments);

        $createdByName = trim((string) ($loanRow->created_by_name_snapshot ?? ''));
        if (($createdByName === '' || $createdByName === '-') && ! empty($loanRow->created_by) && Schema::hasTable('users')) {
            $userColumns = Schema::getColumnListing('users');
            $selectColumns = array_values(array_intersect(['first_name', 'last_name', 'username', 'name'], $userColumns));
            if (! empty($selectColumns)) {
                $createdByUser = DB::table('users')
                    ->select($selectColumns)
                    ->where('id', $loanRow->created_by)
                    ->first();

                if ($createdByUser) {
                    $createdByName = trim(implode(' ', array_filter([
                        $createdByUser->first_name ?? null,
                        $createdByUser->last_name ?? null,
                    ])));
                    if ($createdByName === '') {
                        $createdByName = $createdByUser->username ?? ($createdByUser->name ?? '');
                    }
                }
            }
        }
        $createdByName = Str::of($createdByName !== '' ? $createdByName : '-')->squish()->value();

        $businessName = $locationRow->name ?? $locationName ?? session('business.name', config('app.name'));
        $logo = null;
        $paymentQr = null;
        $telegramQr = null;
        if ($locationRow) {
            $logo = $this->assetFromPublicPath($locationRow->logo_path ?? null);
            $paymentQr = $this->assetFromPublicPath($locationRow->payment_qr_path ?? null);
            $telegramQr = $this->assetFromPublicPath($locationRow->telegram_qr_path ?? null);
        }
        $logo = $logo ?: $this->businessLogoAsset();

        return view('loanmanagement::loans.print.loan', compact(
            'loanRow',
            'customer',
            'locationName',
            'products',
            'installments',
            'payments',
            'businessName',
            'logo',
            'paymentQr',
            'telegramQr',
            'createdByName'
        ));
    }

    public function createPayment(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        abort_if(! Schema::connection('mysql_loan')->hasTable('loan_payments'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $schedules = collect();
        if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            $schedules = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
                ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'due_date') ? 'due_date' : 'id')
                ->orderBy('id')
                ->get();
        }

        $selectedScheduleId = request()->integer('schedule_id') ?: null;
        $selectedSchedule = $selectedScheduleId ? $schedules->firstWhere('id', $selectedScheduleId) : $schedules->first();
        $defaultAmount = $selectedSchedule
            ? (float) ($selectedSchedule->balance_amount ?? $selectedSchedule->amount_balance ?? $selectedSchedule->schedule_amount ?? $selectedSchedule->amount_due ?? 0)
            : (float) ($loanRow->balance_amount ?? 0);
        $payOffAmount = $this->calculatePayOffAmount($schedules, $loanRow);

        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $defaultPaymentMethod = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? '');

        return view('loanmanagement::loans.payments.create', compact(
            'loanRow',
            'schedules',
            'selectedSchedule',
            'selectedScheduleId',
            'defaultAmount',
            'payOffAmount',
            'paymentTypes',
            'defaultPaymentMethod'
        ));
    }

    public function storePayment(Request $request, int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        abort_if(! Schema::connection('mysql_loan')->hasTable('loan_payments'), 404);

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $payload = $request->validate([
            'schedule_id' => 'nullable|integer|min:1',
            'pay_off' => 'nullable|boolean',
            'paid_date' => 'required|date',
            'payment_lines' => 'required|array|min:1',
            'payment_lines.*.amount' => 'required|numeric|min:0.01',
            'payment_lines.*.method' => 'nullable|string|max:100',
            'payment_lines.*.reference_number' => 'nullable|string|max:191',
            'payment_lines.*.note' => 'nullable|string|max:1000',
        ]);

        $paidDate = $payload['paid_date'];
        $paidAt = $paidDate.' '.now()->format('H:i:s');
        $isPayOff = ! empty($payload['pay_off']);
        $selectedScheduleId = $isPayOff ? null : ($payload['schedule_id'] ?? null);
        $paymentTypes = $this->ultimatePosPaymentTypes($loanRow);
        $paymentLines = collect($payload['payment_lines'])
            ->map(function ($line) use ($paymentTypes) {
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $method = trim((string) ($line['method'] ?? ''));

                if ($method === '') {
                    $method = array_key_exists('cash', $paymentTypes) ? 'cash' : (array_key_first($paymentTypes) ?? 'cash');
                }

                return [
                    'amount' => $amount,
                    'method' => $method,
                    'method_name' => $this->paymentMethodName($method, $paymentTypes),
                    'reference_number' => trim((string) ($line['reference_number'] ?? '')) ?: null,
                    'note' => trim((string) ($line['note'] ?? '')) ?: null,
                ];
            })
            ->filter(fn ($line) => $line['amount'] > 0)
            ->values();

        $totalAmount = round((float) $paymentLines->sum('amount'), 2);

        if ($paymentLines->isEmpty() || $totalAmount <= 0) {
            return redirect()
                ->back()
                ->with('status', ['success' => 0, 'msg' => 'Please add at least one payment line.']);
        }

        DB::connection('mysql_loan')->transaction(function () use ($loan, $loanRow, $isPayOff, $selectedScheduleId, $paymentLines, $totalAmount, $paidDate, $paidAt) {
            $userName = trim((string) ((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')));
            if ($userName === '') {
                $userName = auth()->user()->username ?? null;
            }

            foreach ($paymentLines as $line) {
                $receipt = 'RCP-'.now()->format('YmdHis').'-'.$loan.'-'.random_int(10, 99);
                $paymentRef = 'PMT-'.strtoupper(Str::random(10));

                $paymentId = DB::connection('mysql_loan')->table('loan_payments')->insertGetId($this->loanSafeColumns('loan_payments', [
                    'payment_number' => $this->generateUniquePaymentNumber($loan),
                    'payment_ref_no' => $paymentRef,
                    'receipt_number' => $receipt,
                    'loan_id' => $loan,
                    'loan_number_snapshot' => $loanRow->loan_number ?? null,
                    'customer_id' => $loanRow->customer_id ?? 0,
                    'customer_name_snapshot' => $loanRow->customer_name_snapshot ?? null,
                    'schedule_id' => $selectedScheduleId,
                    'received_by' => auth()->id(),
                    'received_by_name_snapshot' => $userName,
                    'collected_by_name_snapshot' => $userName,
                    'channel' => $line['method_name'],
                    'payment_method_snapshot' => $line['method_name'],
                    'amount' => $line['amount'],
                    'total_paid' => $line['amount'],
                    'total_paid_base' => $line['amount'],
                    'currency' => $loanRow->currency ?? 'USD',
                    'base_currency' => $loanRow->currency ?? 'USD',
                    'exchange_rate' => 1,
                    'reference_number' => $line['reference_number'],
                    'paid_date' => $paidDate,
                    'paid_at' => $paidAt,
                    'status' => 'confirmed',
                    'note' => $line['note'],
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                if (Schema::connection('mysql_loan')->hasTable('loan_payment_details')) {
                    DB::connection('mysql_loan')->table('loan_payment_details')->insert($this->loanSafeColumns('loan_payment_details', [
                        'payment_id' => $paymentId,
                        'payment_method_id' => null,
                        'payment_method_snapshot' => $line['method_name'],
                        'method' => $line['method'],
                        'currency' => $loanRow->currency ?? 'USD',
                        'amount' => $line['amount'],
                        'exchange_rate' => 1,
                        'amount_base' => $line['amount'],
                        'reference_number' => $line['reference_number'],
                        'transaction_no' => $line['reference_number'],
                        'note' => $line['note'],
                        'meta_json' => json_encode(['source' => 'loan_detail']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            if ($isPayOff) {
                $this->applyLoanPayOffToSchedules($loan, $totalAmount, $paidAt);
            } else {
                $this->applyLoanPaymentToSchedules($loan, $totalAmount, $paidAt, $selectedScheduleId);
            }
            $this->refreshLoanPaymentTotals($loan, $totalAmount);
        });

        $this->notifyLocationTelegram($loan, 'payment', $totalAmount);

        return redirect()
            ->route('loan-management.loans.view', $loan)
            ->with('status', ['success' => 1, 'msg' => 'Payment added successfully']);
    }

    protected function ultimatePosPaymentTypes(object $loanRow): array
    {
        $businessId = (int) (session('user.business_id') ?? 0);
        $locationId = $loanRow->main_location_id ?? null;

        $paymentTypes = app(TransactionUtil::class)->payment_types($locationId, true, $businessId);

        return ! empty($paymentTypes) ? $paymentTypes : ['cash' => 'Cash'];
    }

    protected function paymentMethodName(string $method, array $paymentTypes): string
    {
        return (string) ($paymentTypes[$method] ?? ucfirst(str_replace('_', ' ', $method)));
    }

    protected function generateUniquePaymentNumber(int $loanId): string
    {
        $prefix = 'PAY-'.now()->format('YmdHis').'-'.$loanId.'-';
        $attempt = 0;

        do {
            $candidate = $prefix.random_int(1000, 9999);
            $exists = DB::connection('mysql_loan')->table('loan_payments')
                ->where('payment_number', $candidate)
                ->exists();
            $attempt++;
        } while ($exists && $attempt < 10);

        return $exists ? $prefix.uniqid() : $candidate;
    }

    protected function calculatePayOffAmount($schedules, object $loanRow): float
    {
        if ($schedules->isEmpty()) {
            return max(0.01, (float) ($loanRow->balance_amount ?? 0));
        }

        $remainingPrincipal = (float) $schedules->sum(function ($schedule) {
            return (float) ($schedule->principal_amount ?? $schedule->principal_due ?? 0);
        });

        $oneMonthInterest = (float) $schedules
            ->map(fn ($schedule) => (float) ($schedule->interest_amount ?? $schedule->interest_due ?? 0))
            ->first(fn ($interest) => $interest > 0, 0);

        $payOffAmount = round($remainingPrincipal + $oneMonthInterest, 2);

        return max(0.01, $payOffAmount > 0 ? $payOffAmount : (float) ($loanRow->balance_amount ?? 0));
    }

    protected function applyLoanPayOffToSchedules(int $loan, float $amount, string $paidAt): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            return;
        }

        $schedules = DB::connection('mysql_loan')->table('loan_payment_schedules')
            ->where('loan_id', $loan)
            ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
            ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'due_date') ? 'due_date' : 'id')
            ->orderBy('id')
            ->get();

        $remaining = $amount;
        foreach ($schedules as $schedule) {
            $currentPaid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
            $principal = (float) ($schedule->principal_amount ?? $schedule->principal_due ?? 0);
            $interest = (float) ($schedule->interest_amount ?? $schedule->interest_due ?? 0);
            $target = max(0, $principal + $interest);
            $applied = min($remaining, $target);

            DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $schedule->id)->update($this->loanSafeColumns('loan_payment_schedules', [
                'amount_paid' => $currentPaid + $applied,
                'paid_amount' => $currentPaid + $applied,
                'amount_balance' => 0,
                'balance_amount' => 0,
                'status' => 'paid',
                'paid_at' => $paidAt,
                'updated_at' => now(),
            ]));

            $remaining = max(0, $remaining - $applied);
        }
    }

    protected function applyLoanPaymentToSchedules(int $loan, float $amount, string $paidAt, ?int $selectedScheduleId = null): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            return;
        }

        $query = DB::connection('mysql_loan')->table('loan_payment_schedules')
            ->where('loan_id', $loan)
            ->whereIn('status', ['pending', 'unpaid', 'partial', 'late']);

        if ($selectedScheduleId) {
            $query->orderByRaw('CASE WHEN id = '.((int) $selectedScheduleId).' THEN 0 ELSE 1 END');
        }

        $schedules = $query
            ->orderBy($this->loanTableHasCol('loan_payment_schedules', 'due_date') ? 'due_date' : 'id')
            ->orderBy('id')
            ->get();

        $remaining = $amount;
        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $due = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? $schedule->schedule_amount ?? $schedule->amount_due ?? 0);
            if ($due <= 0) {
                continue;
            }

            $applied = min($remaining, $due);
            $existingPaidAmount = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
            $newPaid = $existingPaidAmount + $applied;
            $newBalance = max(0, $due - $applied);

            DB::connection('mysql_loan')->table('loan_payment_schedules')->where('id', $schedule->id)->update($this->loanSafeColumns('loan_payment_schedules', [
                'amount_paid' => $newPaid,
                'paid_amount' => $newPaid,
                'amount_balance' => $newBalance,
                'balance_amount' => $newBalance,
                'status' => $newBalance <= 0 ? 'paid' : 'partial',
                'paid_at' => $newBalance <= 0 ? $paidAt : null,
                'updated_at' => now(),
            ]));

            $remaining -= $applied;
        }
    }

    protected function refreshLoanPaymentTotals(int $loan, float $amount): void
    {
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        if (! $loanRow) {
            return;
        }

        $newPaidAmount = (float) ($loanRow->paid_amount ?? 0) + $amount;
        $scheduleBalance = 0.0;
        $hasScheduleBalance = false;
        if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            if ($this->loanTableHasCol('loan_payment_schedules', 'balance_amount')) {
                $scheduleBalance = (float) DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loan)->sum('balance_amount');
                $hasScheduleBalance = true;
            } elseif ($this->loanTableHasCol('loan_payment_schedules', 'amount_balance')) {
                $scheduleBalance = (float) DB::connection('mysql_loan')->table('loan_payment_schedules')->where('loan_id', $loan)->sum('amount_balance');
                $hasScheduleBalance = true;
            }
        }
        $currentBalance = (float) ($loanRow->balance_amount ?? 0);
        $newBalanceAmount = $hasScheduleBalance ? $scheduleBalance : max(0, $currentBalance - $amount);

        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update($this->loanSafeColumns('loans', [
            'paid_amount' => $newPaidAmount,
            'balance_amount' => $newBalanceAmount,
            'status' => $newBalanceAmount <= 0 ? 'completed' : ($loanRow->status ?? 'active'),
            'updated_at' => now(),
        ]));
    }

    protected function notifyLocationTelegram(int $loan, string $event, ?float $amount = null): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans') || ! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return;
        }

        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        if (! $loanRow) {
            return;
        }

        $location = null;
        if (! empty($loanRow->business_location_id)) {
            $location = DB::connection('mysql_loan')->table('loan_business_locations')->where('id', $loanRow->business_location_id)->first();
        }
        if (! $location && ! empty($loanRow->main_location_id)) {
            $location = DB::connection('mysql_loan')->table('loan_business_locations')->where('main_location_id', $loanRow->main_location_id)->first();
        }

        if (! $location) {
            return;
        }

        if ($event === 'payment' && empty($location->telegram_notify_payment)) {
            return;
        }
        if ($event === 'installment' && empty($location->telegram_notify_installment)) {
            return;
        }

        $chatId = $this->telegramChatIdForEvent($location, $event);
        if ($chatId === '') {
            return;
        }

        $message = $event === 'payment'
            ? "Loan payment received\nLoan: ".($loanRow->loan_number ?? $loanRow->id)."\nCustomer: ".($loanRow->customer_name_snapshot ?? '-')."\nLocation: ".($location->name ?? '-')."\nAmount: ".number_format((float) $amount, 2).' '.($loanRow->currency ?? 'USD')."\nBalance: ".number_format((float) ($loanRow->balance_amount ?? 0), 2)
            : "Installment loan created\nLoan: ".($loanRow->loan_number ?? $loanRow->id)."\nCustomer: ".($loanRow->customer_name_snapshot ?? '-')."\nLocation: ".($location->name ?? '-')."\nTotal: ".number_format((float) ($loanRow->principal_amount ?? $loanRow->total_payable_amount ?? 0), 2).' '.($loanRow->currency ?? 'USD');

        try {
            app(TelegramBotService::class)->sendMessageToChat($chatId, $message);
            $this->logTelegramNotification($loanRow, $location, $event, $message, 'sent', null, $chatId);
        } catch (\Throwable $e) {
            Log::warning('LoanManagement Telegram notification failed', [
                'loan_id' => $loan,
                'event' => $event,
                'message' => $e->getMessage(),
            ]);
            $this->logTelegramNotification($loanRow, $location, $event, $message, 'failed', $e->getMessage(), $chatId);
        }
    }

    protected function telegramChatIdForEvent(object $location, string $event): string
    {
        $chatId = $event === 'payment'
            ? ($location->telegram_payment_chat_id ?? null)
            : ($location->telegram_installment_chat_id ?? null);

        return trim((string) ($chatId ?: ($location->telegram_chat_id ?? '')));
    }

    protected function logTelegramNotification(object $loanRow, object $location, string $event, string $message, string $status, ?string $error = null, ?string $chatId = null): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_telegram_notifications')) {
            return;
        }

        DB::connection('mysql_loan')->table('loan_telegram_notifications')->insert($this->loanSafeColumns('loan_telegram_notifications', [
            'customer_id' => $loanRow->customer_id ?? null,
            'loan_id' => $loanRow->id ?? null,
            'event_code' => $event,
            'chat_id' => $chatId ?: $this->telegramChatIdForEvent($location, $event),
            'message' => $error ? ($message."\n\nError: ".$error) : $message,
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function show(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $customerRow = null;
        if (Schema::connection('mysql_loan')->hasTable('loan_customers') && isset($loanRow->customer_id) && $loanRow->customer_id) {
            $customerRow = DB::connection('mysql_loan')->table('loan_customers')->where('id', $loanRow->customer_id)->first();
        }
        $customerDisplayName = $loanRow->customer_name_snapshot ?? null;
        $customerPhoneDisplay = $loanRow->customer_phone_snapshot ?? null;
        $customerAddressDisplay = $loanRow->customer_address_snapshot ?? null;
        $mainContactIdDisplay = $loanRow->main_contact_id ?? null;
        $sourceTypeDisplay = $loanRow->source_type ?? null;
        $sourceTransactionIdDisplay = $loanRow->source_transaction_id ?? null;
        $sourceInvoiceDisplay = $loanRow->source_invoice_no ?? null;
        $sourceFinalTotalDisplay = $loanRow->sell_final_total_snapshot ?? null;
        $sourcePaidDisplay = $loanRow->sell_paid_amount_snapshot ?? null;
        $sourceDueDisplay = $loanRow->sell_due_amount_snapshot ?? null;
        if ($customerRow) {
            $first = isset($customerRow->first_name) ? trim((string) $customerRow->first_name) : '';
            $last = isset($customerRow->last_name) ? trim((string) $customerRow->last_name) : '';
            $fullFromParts = trim($first.' '.$last);
            if (!empty($fullFromParts)) {
                $customerDisplayName = $fullFromParts;
            } elseif (empty($customerDisplayName)) {
                $customerDisplayName = $customerRow->customer_name ?? ($customerRow->name ?? ($customerRow->full_name ?? null));
            }
        }
        if (empty($customerDisplayName)) {
            $customerDisplayName = '-';
        }
        $customerDisplayName = Str::of((string) $customerDisplayName)->squish()->value();

        // Fallback from core contact table if snapshot is incomplete.
        if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($loanRow->main_contact_id) && Schema::hasTable('contacts')) {
            $contactCols = Schema::getColumnListing('contacts');
            $selectCols = array_values(array_intersect(['id', 'name', 'mobile', 'address_line_1'], $contactCols));
            if (!empty($selectCols)) {
                $contact = DB::table('contacts')->select($selectCols)->where('id', $loanRow->main_contact_id)->first();
                if ($contact) {
                    if (!empty($contact->name)) {
                        $customerDisplayName = trim((string) $contact->name);
                    }
                    if (empty($customerAddressDisplay) && !empty($contact->address_line_1)) {
                        $customerAddressDisplay = trim((string) $contact->address_line_1);
                    }
                    if (empty($customerPhoneDisplay) && !empty($contact->mobile)) {
                        $customerPhoneDisplay = trim((string) $contact->mobile);
                    }
                    if (empty($mainContactIdDisplay) && !empty($contact->id)) {
                        $mainContactIdDisplay = (int) $contact->id;
                    }
                }
            }
        }

        // Fallback: if contact id is missing, try resolving customer by phone from core contacts.
        if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($customerPhoneDisplay) && Schema::hasTable('contacts')) {
            $contactCols = Schema::getColumnListing('contacts');
            $selectCols = array_values(array_intersect(['id', 'name', 'mobile', 'address_line_1', 'alternate_number', 'landline'], $contactCols));
            if (!empty($selectCols)) {
                $contact = DB::table('contacts')
                    ->select($selectCols)
                    ->where(function ($q) use ($customerPhoneDisplay, $contactCols) {
                        $q->where('mobile', $customerPhoneDisplay);
                        if (in_array('alternate_number', $contactCols, true)) {
                            $q->orWhere('alternate_number', $customerPhoneDisplay);
                        }
                        if (in_array('landline', $contactCols, true)) {
                            $q->orWhere('landline', $customerPhoneDisplay);
                        }
                    })
                    ->orderByDesc('id')
                    ->first();

                if ($contact) {
                    if (!empty($contact->name)) {
                        $customerDisplayName = trim((string) $contact->name);
                    }
                    if (empty($customerAddressDisplay) && !empty($contact->address_line_1)) {
                        $customerAddressDisplay = trim((string) $contact->address_line_1);
                    }
                    if (empty($mainContactIdDisplay) && !empty($contact->id)) {
                        $mainContactIdDisplay = (int) $contact->id;
                    }
                }
            }
        }

        $locationRow = null;
        if (Schema::connection('mysql_loan')->hasTable('loan_business_locations') && isset($loanRow->business_location_id) && $loanRow->business_location_id) {
            $locationRow = DB::connection('mysql_loan')->table('loan_business_locations')->where('id', $loanRow->business_location_id)->first();
        }
        $locationDisplayName = $loanRow->location_name_snapshot ?? ($locationRow->name ?? null);
        if (empty($locationDisplayName)) {
            $mainLocationId = $loanRow->main_location_id ?? $loanRow->business_location_id ?? null;
            if (!empty($mainLocationId) && Schema::hasTable('business_locations')) {
                $blCols = Schema::getColumnListing('business_locations');
                if (in_array('name', $blCols, true)) {
                    $mainLocation = DB::table('business_locations')
                        ->select(array_intersect(['id', 'name'], $blCols))
                        ->where('id', $mainLocationId)
                        ->first();
                    if ($mainLocation) {
                        $locationDisplayName = trim((string) ($mainLocation->name ?? ''));
                    }
                }
            }
        }
        if (empty($locationDisplayName)) {
            $locationDisplayName = !empty($loanRow->main_location_id)
                ? ('Location #'.$loanRow->main_location_id)
                : (!empty($loanRow->business_location_id) ? ('Location #'.$loanRow->business_location_id) : '-');
        }
        $locationAddressDisplay = null;
        if ($locationRow) {
            $locationAddressDisplay = $locationRow->address
                ?? $locationRow->location_address_snapshot
                ?? $locationRow->landmark
                ?? null;
        }

        // Fallback from source sell when snapshot fields are missing.
        if (!empty($loanRow->source_transaction_id) && Schema::hasTable('transactions')) {
            $source = DB::table('transactions as t')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
                ->where('t.id', $loanRow->source_transaction_id)
                ->selectRaw('t.id, t.type, t.invoice_no, t.final_total, t.location_id, c.name as customer_name, c.mobile as customer_phone, c.address_line_1 as customer_address, bl.name as location_name, bl.landmark as location_landmark')
                ->first();

            if ($source) {
                if (empty($sourceTypeDisplay)) {
                    $sourceTypeDisplay = $source->type;
                }
                if (empty($sourceTransactionIdDisplay)) {
                    $sourceTransactionIdDisplay = $source->id;
                }
                if (empty($sourceInvoiceDisplay)) {
                    $sourceInvoiceDisplay = $source->invoice_no;
                }
                if ((empty($customerDisplayName) || $customerDisplayName === '-') && !empty($source->customer_name)) {
                    $customerDisplayName = trim((string) $source->customer_name);
                }
                if (empty($customerPhoneDisplay) && !empty($source->customer_phone)) {
                    $customerPhoneDisplay = trim((string) $source->customer_phone);
                }
                if (empty($customerAddressDisplay) && !empty($source->customer_address)) {
                    $customerAddressDisplay = trim((string) $source->customer_address);
                }
                if ((empty($locationDisplayName) || $locationDisplayName === '-') && !empty($source->location_name)) {
                    $locationDisplayName = trim((string) $source->location_name);
                }
                if (empty($locationAddressDisplay) && !empty($source->location_landmark)) {
                    $locationAddressDisplay = trim((string) $source->location_landmark);
                }
                if ($sourceFinalTotalDisplay === null && isset($source->final_total)) {
                    $sourceFinalTotalDisplay = $source->final_total;
                }
                if ($sourcePaidDisplay === null || $sourceDueDisplay === null) {
                    $paid = (float) DB::table('transaction_payments')->where('transaction_id', $source->id)->sum('amount');
                    $due = max(0, (float) ($source->final_total ?? 0) - $paid);
                    if ($sourcePaidDisplay === null) {
                        $sourcePaidDisplay = $paid;
                    }
                    if ($sourceDueDisplay === null) {
                        $sourceDueDisplay = $due;
                    }
                }
            }
        }

        $createdByName = $loanRow->created_by_name_snapshot ?? null;
        if (empty($createdByName) && !empty($loanRow->created_by) && Schema::hasTable('users')) {
            $userCols = Schema::getColumnListing('users');
            $namePieces = [];
            if (in_array('first_name', $userCols, true) && in_array('last_name', $userCols, true)) {
                $u = DB::table('users')->select('first_name', 'last_name')->where('id', $loanRow->created_by)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->first_name), trim((string) $u->last_name)];
                }
            } elseif (in_array('username', $userCols, true)) {
                $u = DB::table('users')->select('username')->where('id', $loanRow->created_by)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->username)];
                }
            } elseif (in_array('name', $userCols, true)) {
                $u = DB::table('users')->select('name')->where('id', $loanRow->created_by)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->name)];
                }
            }
            $createdByName = trim(implode(' ', array_filter($namePieces)));
        }
        if (empty($createdByName)) {
            $createdByName = !empty($loanRow->created_by) ? ('User #'.$loanRow->created_by) : '-';
        }
        $createdByName = Str::of((string) $createdByName)->squish()->value();

        $collectorDisplayName = $loanRow->collector_name_snapshot ?? null;
        $collectorUserId = $loanRow->collector_id ?? ($loanRow->assigned_to ?? null);
        if (empty($collectorDisplayName) && !empty($collectorUserId) && Schema::hasTable('users')) {
            $userCols = Schema::getColumnListing('users');
            $namePieces = [];
            if (in_array('first_name', $userCols, true) && in_array('last_name', $userCols, true)) {
                $u = DB::table('users')->select('first_name', 'last_name')->where('id', $collectorUserId)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->first_name), trim((string) $u->last_name)];
                }
            } elseif (in_array('username', $userCols, true)) {
                $u = DB::table('users')->select('username')->where('id', $collectorUserId)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->username)];
                }
            } elseif (in_array('name', $userCols, true)) {
                $u = DB::table('users')->select('name')->where('id', $collectorUserId)->first();
                if ($u) {
                    $namePieces = [trim((string) $u->name)];
                }
            }
            $collectorDisplayName = trim(implode(' ', array_filter($namePieces)));
        }
        if (empty($collectorDisplayName)) {
            $collectorDisplayName = !empty($collectorUserId) ? ('User #'.$collectorUserId) : '-';
        }
        $collectorDisplayName = Str::of((string) $collectorDisplayName)->squish()->value();

        $items = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_items')) {
            $items = DB::connection('mysql_loan')->table('loan_items')->where('loan_id', $loan)->get();
        }

        $productItems = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_product_items')) {
            $productItemsQuery = DB::connection('mysql_loan')->table('loan_product_items');
            if (Schema::connection('mysql_loan')->hasColumn('loan_product_items', 'loan_id')) {
                $productItems = $productItemsQuery->where('loan_id', $loan)->get();
            } elseif (Schema::connection('mysql_loan')->hasColumn('loan_product_items', 'loan_item_id') && $items->count() > 0) {
                $itemIds = $items->pluck('id')->filter()->values();
                $productItems = $itemIds->isEmpty()
                    ? collect([])
                    : $productItemsQuery->whereIn('loan_item_id', $itemIds)->get();
            } else {
                $productItems = collect([]);
            }
        }

        $schedules = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            $schedules = DB::connection('mysql_loan')->table('loan_payment_schedules')
                ->where('loan_id', $loan)
                ->orderBy('due_date')
                ->get();
        }

        $payments = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            $payments = DB::connection('mysql_loan')->table('loan_payments')
                ->where('loan_id', $loan)
                ->orderByDesc('paid_date')
                ->get();
        }

        $statusLogs = [];
        if (Schema::connection('mysql_loan')->hasTable('loan_status_logs')) {
            $statusLogs = DB::connection('mysql_loan')->table('loan_status_logs')
                ->where('loan_id', $loan)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('loanmanagement::loans.show', compact('loanRow', 'customerRow', 'customerDisplayName', 'customerPhoneDisplay', 'customerAddressDisplay', 'mainContactIdDisplay', 'locationRow', 'locationDisplayName', 'locationAddressDisplay', 'sourceTypeDisplay', 'sourceTransactionIdDisplay', 'sourceInvoiceDisplay', 'sourceFinalTotalDisplay', 'sourcePaidDisplay', 'sourceDueDisplay', 'createdByName', 'collectorDisplayName', 'items', 'productItems', 'schedules', 'payments', 'statusLogs'));
    }

    public function edit(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        $loanRow = DB::connection('mysql_loan')->table('loans')->where('id', $loan)->first();
        abort_if(! $loanRow, 404);

        $customerName = trim((string) ($loanRow->customer_name_snapshot ?? ''));
        $customerPhone = trim((string) ($loanRow->customer_phone_snapshot ?? ''));
        $customerAddress = trim((string) ($loanRow->customer_address_snapshot ?? ''));
        $mainContactId = $loanRow->main_contact_id ?? null;

        if ((empty($customerName) || $customerName === '-') && !empty($mainContactId) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')
                ->select('id', 'name', 'mobile', 'address_line_1')
                ->where('id', $mainContactId)
                ->first();
            if ($contact) {
                if (empty($customerName)) $customerName = trim((string) ($contact->name ?? ''));
                if (empty($customerPhone)) $customerPhone = trim((string) ($contact->mobile ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($contact->address_line_1 ?? ''));
            }
        }

        if ((empty($customerName) || $customerName === '-') && !empty($customerPhone) && Schema::hasTable('contacts')) {
            $contact = DB::table('contacts')
                ->select('id', 'name', 'mobile', 'address_line_1', 'alternate_number', 'landline')
                ->where(function ($q) use ($customerPhone) {
                    $q->where('mobile', $customerPhone)
                        ->orWhere('alternate_number', $customerPhone)
                        ->orWhere('landline', $customerPhone);
                })
                ->orderByDesc('id')
                ->first();
            if ($contact) {
                if (empty($customerName)) $customerName = trim((string) ($contact->name ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($contact->address_line_1 ?? ''));
                if (empty($mainContactId)) $mainContactId = (int) $contact->id;
            }
        }

        $locationName = trim((string) ($loanRow->location_name_snapshot ?? ''));
        $locationAddress = '';
        $locationId = $loanRow->main_location_id ?? ($loanRow->business_location_id ?? null);
        if (!empty($locationId) && Schema::hasTable('business_locations')) {
            $loc = DB::table('business_locations')
                ->select('id', 'name', 'landmark')
                ->where('id', $locationId)
                ->first();
            if ($loc) {
                if (empty($locationName)) $locationName = trim((string) ($loc->name ?? ''));
                $locationAddress = trim((string) ($loc->landmark ?? ''));
            }
        }

        $sourceType = $loanRow->source_type ?? null;
        $sourceTransactionId = $loanRow->source_transaction_id ?? null;
        $sourceInvoice = $loanRow->source_invoice_no ?? null;
        $sourceFinalTotal = $loanRow->sell_final_total_snapshot ?? null;
        $sourcePaid = $loanRow->sell_paid_amount_snapshot ?? null;
        $sourceDue = $loanRow->sell_due_amount_snapshot ?? null;

        if (!empty($sourceTransactionId) && Schema::hasTable('transactions')) {
            $source = DB::table('transactions as t')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
                ->where('t.id', $sourceTransactionId)
                ->selectRaw('t.id, t.type, t.invoice_no, t.final_total, c.name as customer_name, c.mobile as customer_phone, c.address_line_1 as customer_address, bl.name as location_name, bl.landmark as location_landmark')
                ->first();
            if ($source) {
                if (empty($sourceType)) $sourceType = $source->type;
                if (empty($sourceInvoice)) $sourceInvoice = $source->invoice_no;
                if (empty($customerName)) $customerName = trim((string) ($source->customer_name ?? ''));
                if (empty($customerPhone)) $customerPhone = trim((string) ($source->customer_phone ?? ''));
                if (empty($customerAddress)) $customerAddress = trim((string) ($source->customer_address ?? ''));
                if (empty($locationName)) $locationName = trim((string) ($source->location_name ?? ''));
                if (empty($locationAddress)) $locationAddress = trim((string) ($source->location_landmark ?? ''));
                if ($sourceFinalTotal === null) $sourceFinalTotal = $source->final_total;
                if ($sourcePaid === null || $sourceDue === null) {
                    $paid = (float) DB::table('transaction_payments')->where('transaction_id', $source->id)->sum('amount');
                    $due = max(0, (float) ($source->final_total ?? 0) - $paid);
                    if ($sourcePaid === null) $sourcePaid = $paid;
                    if ($sourceDue === null) $sourceDue = $due;
                }
            }
        }

        $customerName = $customerName !== '' ? $customerName : '-';
        $customerPhone = $customerPhone !== '' ? $customerPhone : '-';
        $customerAddress = $customerAddress !== '' ? $customerAddress : '-';
        $locationName = $locationName !== '' ? $locationName : '-';
        $locationAddress = $locationAddress !== '' ? $locationAddress : '-';

        return view('loanmanagement::loans.edit', compact(
            'loanRow',
            'customerName',
            'customerPhone',
            'customerAddress',
            'mainContactId',
            'locationName',
            'locationAddress',
            'locationId',
            'sourceType',
            'sourceTransactionId',
            'sourceInvoice',
            'sourceFinalTotal',
            'sourcePaid',
            'sourceDue'
        ));
    }

    public function update(Request $request, int $loan)
    {
        $data = $request->validate([
            'loan_date' => 'nullable|date',
            'principal_amount' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'duration_months' => 'nullable|integer|min:1|max:360',
            'note' => 'nullable|string|max:1000',
        ]);

        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update(array_merge($data, ['updated_at' => now()]));

        return redirect()->route('loan-management.loans.view', $loan)->with('status', 'Loan updated successfully.');
    }

    public function changeStatus(Request $request, int $loan)
    {
        $payload = $request->validate([
            'status' => 'required|in:draft,pending,approved,active,completed,rejected,cancelled,defaulted',
        ]);

        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->update([
            'status' => $payload['status'],
            'updated_at' => now(),
        ]);

        if (Schema::connection('mysql_loan')->hasTable('loan_status_logs')) {
            $cols = Schema::connection('mysql_loan')->getColumnListing('loan_status_logs');
            $row = [
                'loan_id' => $loan,
                'status' => $payload['status'],
                'changed_by' => auth()->id(),
                'note' => 'Status changed from installment list',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::connection('mysql_loan')->table('loan_status_logs')->insert(array_intersect_key($row, array_flip($cols)));
        }

        return response()->json(['success' => true, 'message' => 'Status updated']);
    }

    public function destroy(int $loan)
    {
        abort_if(! Schema::connection('mysql_loan')->hasTable('loans'), 404);
        DB::connection('mysql_loan')->table('loans')->where('id', $loan)->delete();
        return response()->json(['success' => true, 'message' => 'Loan deleted']);
    }
}
