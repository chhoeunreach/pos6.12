<?php

namespace App\Http\Controllers;

use App\Account;
use App\BusinessLocation;
use App\InvoiceLayout;
use App\InvoiceScheme;
use App\SellingPriceGroup;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class BusinessLocationController extends Controller
{
    protected $moduleUtil;

    protected $commonUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, Util $commonUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $locations = BusinessLocation::where('business_locations.business_id', $business_id)
                ->leftjoin(
                    'invoice_schemes as ic',
                    'business_locations.invoice_scheme_id',
                    '=',
                    'ic.id'
                )
                ->leftjoin(
                    'invoice_layouts as il',
                    'business_locations.invoice_layout_id',
                    '=',
                    'il.id'
                )
                ->leftjoin(
                    'invoice_layouts as sil',
                    'business_locations.sale_invoice_layout_id',
                    '=',
                    'sil.id'
                )
                ->leftjoin(
                    'selling_price_groups as spg',
                    'business_locations.selling_price_group_id',
                    '=',
                    'spg.id'
                )
                ->select(['business_locations.name', 'location_id', 'landmark', 'city', 'zip_code', 'state',
                    'country', 'business_locations.id', 'spg.name as price_group', 'ic.name as invoice_scheme', 'il.name as invoice_layout', 'sil.name as sale_invoice_layout', 'business_locations.is_active', ]);

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $locations->whereIn('business_locations.id', $permitted_locations);
            }

            return Datatables::of($locations)
                ->addColumn(
                    'action',
                    '<button type="button" data-href="{{action(\'App\Http\Controllers\BusinessLocationController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".location_edit_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                    <a href="{{route(\'location.settings\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-accent"><i class="fa fa-wrench"></i> @lang("messages.settings")</a>

                    <button type="button" data-href="{{action(\'App\Http\Controllers\BusinessLocationController@activateDeactivateLocation\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline   activate-deactivate-location @if($is_active) tw-dw-btn-error @else tw-dw-btn-accent @endif tw-w-max"><i class="fa fa-power-off"></i> @if($is_active) @lang("lang_v1.deactivate_location") @else @lang("lang_v1.activate_location") @endif </button>
                    '
                )
                ->removeColumn('id')
                ->removeColumn('is_active')
                ->rawColumns([11])
                ->make(false);
        }

        return view('business_location.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for location quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('locations', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('locations', $business_id);
        }

        $invoice_layouts = InvoiceLayout::where('business_id', $business_id)
                            ->get()
                            ->pluck('name', 'id');

        $invoice_schemes = InvoiceScheme::where('business_id', $business_id)
                            ->get()
                            ->pluck('name', 'id');

        $price_groups = SellingPriceGroup::forDropdown($business_id);

        $payment_types = $this->commonUtil->payment_types(null, false, $business_id);

        //Accounts
        $accounts = [];
        if ($this->commonUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        return view('business_location.create')
                    ->with(compact(
                        'invoice_layouts',
                        'invoice_schemes',
                        'price_groups',
                        'payment_types',
                        'accounts'
                    ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            //Check if subscribed or not, then check for location quota
            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            } elseif (! $this->moduleUtil->isQuotaAvailable('locations', $business_id)) {
                return $this->moduleUtil->quotaExpiredResponse('locations', $business_id);
            }

            $input = $request->only(['name', 'landmark', 'city', 'state', 'country', 'zip_code', 'invoice_scheme_id',
                'invoice_layout_id', 'mobile', 'alternate_number', 'email', 'website', 'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 'location_id', 'selling_price_group_id', 'default_payment_accounts', 'featured_products', 'sale_invoice_layout_id', 'sale_invoice_scheme_id']);

            $input['business_id'] = $business_id;

            $input['default_payment_accounts'] = ! empty($input['default_payment_accounts']) ? json_encode($input['default_payment_accounts']) : null;

            //Update reference count
            $ref_count = $this->moduleUtil->setAndGetReferenceCount('business_location');

            if (empty($input['location_id'])) {
                $input['location_id'] = $this->moduleUtil->generateReferenceNumber('business_location', $ref_count);
            }

            $location = BusinessLocation::create($input);

            //Create a new permission related to the created location
            Permission::create(['name' => 'location.'.$location->id]);

            $output = ['success' => true,
                'msg' => __('business.business_location_added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\StoreFront  $storeFront
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\StoreFront  $storeFront
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $location = BusinessLocation::where('business_id', $business_id)
                                    ->find($id);
        $invoice_layouts = InvoiceLayout::where('business_id', $business_id)
                            ->get()
                            ->pluck('name', 'id');
        $invoice_schemes = InvoiceScheme::where('business_id', $business_id)
                            ->get()
                            ->pluck('name', 'id');

        $price_groups = SellingPriceGroup::forDropdown($business_id);

        $payment_types = $this->commonUtil->payment_types(null, false, $business_id);

        //Accounts
        $accounts = [];
        if ($this->commonUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }
        $featured_products = $location->getFeaturedProducts(true, false);

        return view('business_location.edit')
                ->with(compact(
                    'location',
                    'invoice_layouts',
                    'invoice_schemes',
                    'price_groups',
                    'payment_types',
                    'accounts',
                    'featured_products'
                ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\StoreFront  $storeFront
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'landmark', 'city', 'state', 'country',
                'zip_code', 'invoice_scheme_id',
                'invoice_layout_id', 'mobile', 'alternate_number', 'email', 'website', 'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 'location_id', 'selling_price_group_id', 'default_payment_accounts', 'featured_products', 'sale_invoice_layout_id', 'sale_invoice_scheme_id' ]);

            $business_id = $request->session()->get('user.business_id');

            $input['default_payment_accounts'] = ! empty($input['default_payment_accounts']) ? json_encode($input['default_payment_accounts']) : null;

            $input['featured_products'] = ! empty($input['featured_products']) ? json_encode($input['featured_products']) : null;

            BusinessLocation::where('business_id', $business_id)
                            ->where('id', $id)
                            ->update($input);

            $output = ['success' => true,
                'msg' => __('business.business_location_updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\StoreFront  $storeFront
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Checks if the given location id already exist for the current business.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkLocationId(Request $request)
    {
        $location_id = $request->input('location_id');

        $valid = 'true';
        if (! empty($location_id)) {
            $business_id = $request->session()->get('user.business_id');
            $hidden_id = $request->input('hidden_id');

            $query = BusinessLocation::where('business_id', $business_id)
                            ->where('location_id', $location_id);
            if (! empty($hidden_id)) {
                $query->where('id', '!=', $hidden_id);
            }
            $count = $query->count();
            if ($count > 0) {
                $valid = 'false';
            }
        }
        echo $valid;
        exit;
    }

    /**
     * Function to activate or deactivate a location.
     *
     * @param  int  $location_id
     * @return json
     */
    public function activateDeactivateLocation($location_id)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $business_location = BusinessLocation::where('business_id', $business_id)
                            ->findOrFail($location_id);

            $business_location->is_active = ! $business_location->is_active;
            $business_location->save();

            $msg = $business_location->is_active ? __('lang_v1.business_location_activated_successfully') : __('lang_v1.business_location_deactivated_successfully');

            $output = ['success' => true,
                'msg' => $msg,
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    public function downloadTemplate(Request $request)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = $request->session()->get('user.business_id');
        $this->ensureAdminOrSuperadmin($business_id);

        $columns = [
            'name',
            'location_id',
            'landmark',
            'country',
            'state',
            'city',
            'zip_code',
            'invoice_scheme',
            'invoice_layout',
            'selling_price_group',
            'mobile',
            'alternate_number',
            'email',
            'website',
            'custom_field1',
            'custom_field2',
            'custom_field3',
            'custom_field4',
            'is_active',
        ];

        $filename = 'business_locations_template_' . date('Ymd_His') . '.csv';

        $callback = function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fclose($out);
        };

        Log::info('Business location import template downloaded', [
            'business_id' => $business_id,
            'user_id' => $request->session()->get('user.id'),
        ]);

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    public function export(Request $request)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $this->ensureAdminOrSuperadmin($business_id);
        $include_inactive = $request->boolean('include_inactive', false);
        $format = strtolower((string) $request->input('format', 'csv'));
        $format = in_array($format, ['csv', 'xlsx'], true) ? $format : 'csv';

        $locations = BusinessLocation::where('business_id', $business_id)
            ->when(! $include_inactive, fn ($q) => $q->where('is_active', 1))
            ->orderBy('name')
            ->get();

        $invoiceSchemes = DB::table('invoice_schemes')->where('business_id', $business_id)->pluck('name', 'id');
        $invoiceLayouts = DB::table('invoice_layouts')->where('business_id', $business_id)->pluck('name', 'id');
        $priceGroups = DB::table('selling_price_groups')->where('business_id', $business_id)->pluck('name', 'id');

        $rows = [];
        foreach ($locations as $loc) {
            $rows[] = [
                'name' => $loc->name,
                'location_id' => $loc->location_id,
                'landmark' => $loc->landmark,
                'country' => $loc->country,
                'state' => $loc->state,
                'city' => $loc->city,
                'zip_code' => $loc->zip_code,
                'invoice_scheme' => $invoiceSchemes[$loc->invoice_scheme_id] ?? null,
                'invoice_layout' => $invoiceLayouts[$loc->invoice_layout_id] ?? null,
                'selling_price_group' => $priceGroups[$loc->selling_price_group_id] ?? null,
                'mobile' => $loc->mobile,
                'alternate_number' => $loc->alternate_number,
                'email' => $loc->email,
                'website' => $loc->website,
                'custom_field1' => $loc->custom_field1,
                'custom_field2' => $loc->custom_field2,
                'custom_field3' => $loc->custom_field3,
                'custom_field4' => $loc->custom_field4,
                'is_active' => (int) ($loc->is_active ?? 0),
            ];
        }

        $filename = 'business_locations_' . date('Ymd_His') . '.' . $format;

        Log::info('Business location export', [
            'business_id' => $business_id,
            'user_id' => $request->session()->get('user.id'),
            'format' => $format,
            'include_inactive' => $include_inactive,
            'count' => count($rows),
        ]);

        if ($format === 'xlsx') {
            return Excel::download(new \App\Exports\ArrayExport($rows), $filename);
        }

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys($rows[0] ?? [
                'name' => null,
                'location_id' => null,
                'landmark' => null,
                'country' => null,
                'state' => null,
                'city' => null,
                'zip_code' => null,
                'invoice_scheme' => null,
                'invoice_layout' => null,
                'selling_price_group' => null,
                'mobile' => null,
                'alternate_number' => null,
                'email' => null,
                'website' => null,
                'custom_field1' => null,
                'custom_field2' => null,
                'custom_field3' => null,
                'custom_field4' => null,
                'is_active' => null,
            ]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    public function importPreview(Request $request)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'file' => 'required|file|max:5120',
            'mode' => 'required|in:insert,update,upsert',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $this->ensureAdminOrSuperadmin($business_id);
        $user_id = $request->session()->get('user.id');
        $mode = $request->input('mode', 'insert');

        try {
            $file = $request->file('file');
            $sheets = Excel::toArray(new \App\Imports\BusinessLocationsImportPreview, $file);
            $rawRows = $sheets[0] ?? [];

            $normalizedRows = [];
            $names = [];
            $locationIds = [];
            $rowNumber = 1; // heading row is 1
            foreach ($rawRows as $row) {
                $rowNumber++;
                $row = array_change_key_case(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row), CASE_LOWER);
                if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $locationId = trim((string) ($row['location_id'] ?? ''));
                $names[] = $name;
                if ($locationId !== '') {
                    $locationIds[] = $locationId;
                }
                $normalizedRows[] = [
                    'row_number' => $rowNumber,
                    'data' => $row,
                ];
            }

            $existingByName = BusinessLocation::where('business_id', $business_id)
                ->whereIn('name', array_values(array_unique(array_filter($names))))
                ->get()
                ->keyBy('name');

            $existingByLocationId = BusinessLocation::where('business_id', $business_id)
                ->whereIn('location_id', array_values(array_unique(array_filter($locationIds))))
                ->get()
                ->keyBy('location_id');

            $locationIdCounts = [];
            foreach (array_filter($locationIds) as $locationId) {
                $locationIdCounts[$locationId] = ($locationIdCounts[$locationId] ?? 0) + 1;
            }

            $summary = [
                'total_rows' => count($normalizedRows),
                'new_rows' => 0,
                'existing_rows' => 0,
                'skipped_rows' => 0,
                'error_rows' => 0,
            ];

            $previewRows = [];
            foreach ($normalizedRows as $rowInfo) {
                $row = $rowInfo['data'];
                $row_no = $rowInfo['row_number'];
                $errors = [];

                $name = trim((string) ($row['name'] ?? ''));
                $locationId = trim((string) ($row['location_id'] ?? ''));
                if ($name === '') {
                    $errors[] = 'name is required';
                }
                if ($locationId !== '' && ($locationIdCounts[$locationId] ?? 0) > 1) {
                    $errors[] = 'duplicate location_id in file';
                }
                if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'invalid email';
                }

                $is_active_val = $row['is_active'] ?? null;
                $parsed_active = $this->parseIsActive($is_active_val, $errors);

                $existing = $this->findExistingLocationForImport(
                    $name,
                    $locationId,
                    $existingByName,
                    $existingByLocationId,
                    $errors
                );
                $exists = ! empty($existing);
                $status = 'new';
                $action = 'insert';
                if ($exists) {
                    $status = 'existing';
                    $action = 'update';
                }

                if ($mode === 'insert' && $exists) {
                    $action = 'skip';
                } elseif ($mode === 'update' && ! $exists) {
                    $action = 'skip';
                }

                if (! empty($errors)) {
                    $status = 'error';
                    $action = 'skip';
                }

                if ($status === 'error') {
                    $summary['error_rows']++;
                } elseif ($exists) {
                    $summary['existing_rows']++;
                } else {
                    $summary['new_rows']++;
                }
                if ($action === 'skip') {
                    $summary['skipped_rows']++;
                }

                $previewRows[] = [
                    'row_number' => $row_no,
                    'name' => $name,
                    'location_id' => $locationId,
                    'status' => $status,
                    'action' => $action,
                    'errors' => $errors,
                    'parsed' => [
                        'is_active' => $parsed_active,
                    ],
                    'data' => $row,
                ];
            }

            $token = Str::random(40);
            Cache::put('bl_import_preview:' . $token, [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'mode' => $mode,
                'rows' => $previewRows,
                'created_at' => now()->toDateTimeString(),
            ], now()->addMinutes(30));

            Log::info('Business location import preview', [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'mode' => $mode,
                'total_rows' => $summary['total_rows'],
                'error_rows' => $summary['error_rows'],
            ]);

            return [
                'success' => true,
                'token' => $token,
                'summary' => $summary,
                'rows' => $previewRows,
            ];
        } catch (\Exception $e) {
            Log::emergency('BL import preview error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }

    public function importConfirm(Request $request)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'token' => 'required|string',
            'mode' => 'required|in:insert,update,upsert',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $this->ensureAdminOrSuperadmin($business_id);
        $user_id = $request->session()->get('user.id');
        $token = $request->input('token');
        $mode = $request->input('mode');

        $payload = Cache::get('bl_import_preview:' . $token);
        if (empty($payload) || ($payload['business_id'] ?? null) !== $business_id || ($payload['user_id'] ?? null) !== $user_id) {
            return ['success' => false, 'msg' => 'Import preview expired. Please preview again.'];
        }

        if (($payload['mode'] ?? null) !== $mode) {
            return ['success' => false, 'msg' => 'Import mode mismatch. Please preview again.'];
        }

        $rows = $payload['rows'] ?? [];
        $errorRows = array_filter($rows, fn ($r) => ($r['status'] ?? '') === 'error');
        if (! empty($errorRows)) {
            return ['success' => false, 'msg' => 'Fix import errors in preview before confirming.'];
        }

        try {
            $result = DB::transaction(function () use ($rows, $business_id, $user_id, $mode) {
                $updated = 0;
                $inserted = 0;
                $skipped = 0;

                $invoiceSchemes = DB::table('invoice_schemes')->where('business_id', $business_id)->pluck('id', 'name');
                $invoiceSchemesById = DB::table('invoice_schemes')->where('business_id', $business_id)->pluck('id', 'id');
                $invoiceLayouts = DB::table('invoice_layouts')->where('business_id', $business_id)->pluck('id', 'name');
                $invoiceLayoutsById = DB::table('invoice_layouts')->where('business_id', $business_id)->pluck('id', 'id');
                $priceGroups = DB::table('selling_price_groups')->where('business_id', $business_id)->pluck('id', 'name');
                $priceGroupsById = DB::table('selling_price_groups')->where('business_id', $business_id)->pluck('id', 'id');
                $existingLocations = BusinessLocation::where('business_id', $business_id)->get();
                $existingByName = $existingLocations->keyBy('name');
                $existingByLocationId = $existingLocations->filter(function ($location) {
                    return ! empty($location->location_id);
                })->keyBy('location_id');

                $hasIsActive = Schema::hasColumn('business_locations', 'is_active');

                foreach ($rows as $row) {
                    $action = $row['action'] ?? 'skip';
                    if ($action === 'skip') {
                        $skipped++;
                        continue;
                    }

                    $data = $row['data'] ?? [];
                    $name = trim((string) ($data['name'] ?? ''));
                    if ($name === '') {
                        throw new \Exception('Invalid row: name is required.');
                    }

                    $input = [
                        'business_id' => $business_id,
                        'name' => $name,
                        'location_id' => $this->nullableTrimmedString($data['location_id'] ?? null),
                        'landmark' => $data['landmark'] ?? null,
                        'country' => $data['country'] ?? null,
                        'state' => $data['state'] ?? null,
                        'city' => $data['city'] ?? null,
                        'zip_code' => $data['zip_code'] ?? null,
                        'mobile' => $data['mobile'] ?? null,
                        'alternate_number' => $data['alternate_number'] ?? null,
                        'email' => $data['email'] ?? null,
                        'website' => $data['website'] ?? null,
                        'custom_field1' => $data['custom_field1'] ?? null,
                        'custom_field2' => $data['custom_field2'] ?? null,
                        'custom_field3' => $data['custom_field3'] ?? null,
                        'custom_field4' => $data['custom_field4'] ?? null,
                    ];

                    if ($hasIsActive) {
                        $errors = [];
                        $input['is_active'] = $this->parseIsActive($data['is_active'] ?? null, $errors);
                    }

                    $input['invoice_scheme_id'] = $this->resolveLookupId($data['invoice_scheme'] ?? null, $invoiceSchemes, $invoiceSchemesById);
                    $input['invoice_layout_id'] = $this->resolveLookupId($data['invoice_layout'] ?? null, $invoiceLayouts, $invoiceLayoutsById);
                    $input['selling_price_group_id'] = $this->resolveLookupId($data['selling_price_group'] ?? null, $priceGroups, $priceGroupsById);

                    $existing = $this->findExistingLocationForImport(
                        $name,
                        trim((string) ($data['location_id'] ?? '')),
                        $existingByName,
                        $existingByLocationId
                    );

                    if ($existing) {
                        if ($mode === 'insert') {
                            $skipped++;
                            continue;
                        }
                        $existing->fill($input);
                        $existing->save();
                        $updated++;
                    } else {
                        if ($mode === 'update') {
                            $skipped++;
                            continue;
                        }
                        $created = BusinessLocation::create($input);
                        Permission::create(['name' => 'location.' . $created->id]);
                        $inserted++;
                    }
                }

                return compact('inserted', 'updated', 'skipped');
            });

            Cache::forget('bl_import_preview:' . $token);

            Log::info('Business location import confirm', [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'mode' => $mode,
                'result' => $result,
            ]);

            return [
                'success' => true,
                'msg' => "Import completed. Inserted: {$result['inserted']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}",
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::emergency('BL import confirm error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => $e->getMessage() ?: __('messages.something_went_wrong'),
            ];
        }
    }

    private function parseIsActive($value, array &$errors = [])
    {
        if ($value === null || $value === '') {
            return 1;
        }

        if (is_numeric($value)) {
            $v = (int) $value;
            if ($v === 0 || $v === 1) {
                return $v;
            }
        }

        $v = strtolower(trim((string) $value));
        $map = [
            '1' => 1,
            '0' => 0,
            'yes' => 1,
            'no' => 0,
            'active' => 1,
            'inactive' => 0,
            'true' => 1,
            'false' => 0,
        ];
        if (array_key_exists($v, $map)) {
            return $map[$v];
        }

        $errors[] = 'invalid is_active (use 1/0, yes/no, active/inactive)';

        return 1;
    }

    private function resolveLookupId($value, $nameToIdMap, $idToIdMap)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $valueStr = trim((string) $value);
        if ($valueStr === '') {
            return null;
        }

        if (is_numeric($valueStr) && isset($idToIdMap[(int) $valueStr])) {
            return (int) $valueStr;
        }

        return $nameToIdMap[$valueStr] ?? null;
    }

    private function ensureAdminOrSuperadmin($business_id): void
    {
        if (! $this->commonUtil->is_admin(auth()->user(), $business_id)) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function findExistingLocationForImport(
        string $name,
        string $locationId,
        $existingByName,
        $existingByLocationId,
        array &$errors = []
    ) {
        $existingByNameMatch = $name !== '' ? ($existingByName[$name] ?? null) : null;
        $existingByLocationIdMatch = $locationId !== '' ? ($existingByLocationId[$locationId] ?? null) : null;

        if (! empty($existingByNameMatch) && ! empty($existingByLocationIdMatch) && (int) $existingByNameMatch->id !== (int) $existingByLocationIdMatch->id) {
            $errors[] = 'name and location_id match different existing locations';

            return null;
        }

        return $existingByLocationIdMatch ?? $existingByNameMatch;
    }

    private function nullableTrimmedString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
