<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Imports\UsersImportPreview;
use App\BusinessLocation;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use App\Events\UserCreatedOrModified;

class ManageUserController extends Controller
{
    protected $commonUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
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
        if (! auth()->user()->can('user.view') && ! auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $users = User::where('business_id', $business_id)
                        ->user()
                        ->where('is_cmmsn_agnt', 0)
                        ->select(['id', 'username',
                            DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"), 'email', 'allow_login', ]);

            return Datatables::of($users)
                ->editColumn('username', '{{$username}} @if(empty($allow_login)) <span class="label bg-gray">@lang("lang_v1.login_not_allowed")</span>@endif')
                ->addColumn(
                    'role',
                    function ($row) {
                        $role_name = $this->moduleUtil->getUserRoleName($row->id);

                        return $role_name;
                    }
                )
                ->addColumn(
                    'action',
                    '@can("user.update")
                        <a href="{{action(\'App\Http\Controllers\ManageUserController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a>
                        &nbsp;
                    @endcan
                    @can("user.view")
                    <a href="{{action(\'App\Http\Controllers\ManageUserController@show\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info"><i class="fa fa-eye"></i> @lang("messages.view")</a>
                    &nbsp;
                    @endcan
                    @can("user.delete")
                        <button data-href="{{action(\'App\Http\Controllers\ManageUserController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_user_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endcan'
                )
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'username'])
                ->make(true);
        }

        $can_user_import_export = $this->canManageUserImportExport();

        return view('manage_user.index')->with(compact('can_user_import_export'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('users', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('users', $business_id, action([\App\Http\Controllers\ManageUserController::class, 'index']));
        }

        $roles = $this->getRolesArray($business_id);
        $username_ext = $this->moduleUtil->getUsernameExtension();
        $locations = BusinessLocation::where('business_id', $business_id)
                                    ->Active()
                                    ->get();

        //Get user form part from modules
        $form_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'manage_user.create']);

        return view('manage_user.create')
                ->with(compact('roles', 'username_ext', 'locations', 'form_partials'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            if (! empty($request->input('dob'))) {
                $request['dob'] = $this->moduleUtil->uf_date($request->input('dob'));
            }

            $request['cmmsn_percent'] = ! empty($request->input('cmmsn_percent')) ? $this->moduleUtil->num_uf($request->input('cmmsn_percent')) : 0;

            $request['max_sales_discount_percent'] = ! is_null($request->input('max_sales_discount_percent')) ? $this->moduleUtil->num_uf($request->input('max_sales_discount_percent')) : null;

            $user = $this->moduleUtil->createUser($request);

            event(new UserCreatedOrModified($user, 'added'));

            $output = ['success' => 1,
                'msg' => __('user.user_added'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('users')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('user.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $user = User::where('business_id', $business_id)
                    ->with(['contactAccess'])
                    ->find($id);

        //Get user view part from modules
        $view_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'manage_user.show', 'user' => $user]);

        $users = User::forDropdown($business_id, false);

        $activities = Activity::forSubject($user)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        return view('manage_user.show')->with(compact('user', 'view_partials', 'users', 'activities'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('user.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $user = User::where('business_id', $business_id)
                    ->with(['contactAccess'])
                    ->findOrFail($id);

        $roles = $this->getRolesArray($business_id);

        $contact_access = $user->contactAccess->pluck('name', 'id')->toArray();

        if ($user->status == 'active') {
            $is_checked_checkbox = true;
        } else {
            $is_checked_checkbox = false;
        }

        $locations = BusinessLocation::where('business_id', $business_id)
                                    ->get();

        $permitted_locations = $user->permitted_locations();
        $username_ext = $this->moduleUtil->getUsernameExtension();

        //Get user form part from modules
        $form_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'manage_user.edit', 'user' => $user]);

        return view('manage_user.edit')
                ->with(compact('roles', 'user', 'contact_access', 'is_checked_checkbox', 'locations', 'permitted_locations', 'form_partials', 'username_ext'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //Disable in demo
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }
        
        if (! auth()->user()->can('user.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

         //Check if subscribed
         if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        //Check for users quota if allow_login is true
        if (!empty($request->input('allow_login'))) {
            if (! $this->moduleUtil->isQuotaAvailable('users', $business_id)) {
                return $this->moduleUtil->quotaExpiredResponse('users', $business_id, action([\App\Http\Controllers\ManageUserController::class, 'index']));
            }
        }

        try {
            $user_data = $request->only(['surname', 'first_name', 'last_name', 'email', 'selected_contacts', 'marital_status',
                'blood_group', 'contact_number', 'fb_link', 'twitter_link', 'social_media_1',
                'social_media_2', 'permanent_address', 'current_address',
                'guardian_name', 'custom_field_1', 'custom_field_2',
                'custom_field_3', 'custom_field_4', 'id_proof_name', 'id_proof_number', 'cmmsn_percent', 'gender', 'max_sales_discount_percent', 'family_number', 'alt_number', 'is_enable_service_staff_pin']);

            $user_data['status'] = ! empty($request->input('is_active')) ? 'active' : 'inactive';

            $user_data['is_enable_service_staff_pin'] = ! empty($request->input('is_enable_service_staff_pin')) ? true : false;

           

            if (! isset($user_data['selected_contacts'])) {
                $user_data['selected_contacts'] = 0;
            }

            if (empty($request->input('allow_login'))) {
                $user_data['username'] = null;
                $user_data['password'] = null;
                $user_data['allow_login'] = 0;
            } else {
                $user_data['allow_login'] = 1;
            }

            if (! empty($request->input('password'))) {
                $user_data['password'] = $user_data['allow_login'] == 1 ? Hash::make($request->input('password')) : null;
            }


            if (! empty($request->input('service_staff_pin'))) {
                $user_data['service_staff_pin'] = $request->input('service_staff_pin');
            }
            

            //Sales commission percentage
            $user_data['cmmsn_percent'] = ! empty($user_data['cmmsn_percent']) ? $this->moduleUtil->num_uf($user_data['cmmsn_percent']) : 0;

            $user_data['max_sales_discount_percent'] = ! is_null($user_data['max_sales_discount_percent']) ? $this->moduleUtil->num_uf($user_data['max_sales_discount_percent']) : null;

            if (! empty($request->input('dob'))) {
                $user_data['dob'] = $this->moduleUtil->uf_date($request->input('dob'));
            }

            if (! empty($request->input('bank_details'))) {
                $user_data['bank_details'] = json_encode($request->input('bank_details'));
            }

            DB::beginTransaction();

            if ($user_data['allow_login'] && $request->has('username')) {
                $user_data['username'] = $request->input('username');
                $ref_count = $this->moduleUtil->setAndGetReferenceCount('username');
                if (blank($user_data['username'])) {
                    $user_data['username'] = $this->moduleUtil->generateReferenceNumber('username', $ref_count);
                }

                $username_ext = $this->moduleUtil->getUsernameExtension();
                if (! empty($username_ext)) {
                    $user_data['username'] .= $username_ext;
                }
            }

            $user = User::where('business_id', $business_id)
                          ->findOrFail($id);

            $user->update($user_data);
            $role_id = $request->input('role');
            $user_role = $user->roles->first();
            $previous_role = ! empty($user_role->id) ? $user_role->id : 0;
            if ($previous_role != $role_id) {
                $is_admin = $this->moduleUtil->is_admin($user);
                $all_admins = $this->getAdmins();
                //If only one admin then can not change role
                if ($is_admin && count($all_admins) <= 1) {
                    throw new \Exception(__('lang_v1.cannot_change_role'));
                }
                if (! empty($previous_role)) {
                    $user->removeRole($user_role->name);
                }

                $role = Role::findOrFail($role_id);
                $user->assignRole($role->name);
            }

            //Grant Location permissions
            $this->moduleUtil->giveLocationPermissions($user, $request);

            //Assign selected contacts
            if ($user_data['selected_contacts'] == 1) {
                $contact_ids = $request->get('selected_contact_ids');
            } else {
                $contact_ids = [];
            }
            $user->contactAccess()->sync($contact_ids);

            //Update module fields for user
            $this->moduleUtil->getModuleData('afterModelSaved', ['event' => 'user_saved', 'model_instance' => $user]);

            $this->moduleUtil->activityLog($user, 'edited', null, ['name' => $user->user_full_name]);
           
            event(new UserCreatedOrModified($user, 'updated'));
            
            $output = ['success' => 1,
                'msg' => __('user.user_update_success'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect('users')->with('status', $output);
    }

    private function getAdmins()
    {
        $business_id = request()->session()->get('user.business_id');
        $admins = User::role('Admin#'.$business_id)->get();

        return $admins;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Disable in demo
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        if (! auth()->user()->can('user.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $user = User::where('business_id', $business_id)
                    ->findOrFail($id);

                $this->moduleUtil->activityLog($user, 'deleted', null, ['name' => $user->user_full_name, 'id' => $user->id]);

                $user->delete();
                event(new UserCreatedOrModified($user, 'deleted'));

                $output = ['success' => true,
                    'msg' => __('user.user_delete_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function downloadTemplate(Request $request)
    {
        $this->authorizeUserImportExport('view');

        $columns = [
            'surname',
            'first_name',
            'last_name',
            'username',
            'email',
            'contact_no',
            'password',
            'status',
            'role',
            'allow_login',
            'language',
            'address',
            'location_names',
        ];

        $filename = 'users_import_template_' . date('Ymd_His') . '.csv';

        Log::info('Users import template downloaded', [
            'business_id' => (int) $request->session()->get('user.business_id'),
            'user_id' => (int) $request->session()->get('user.id'),
        ]);

        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportUsers(Request $request)
    {
        $this->authorizeUserImportExport('export');

        $business_id = (int) $request->session()->get('user.business_id');
        $format = strtolower((string) $request->input('format', 'csv'));
        $format = in_array($format, ['csv', 'xlsx'], true) ? $format : 'csv';
        $include_inactive = (bool) $request->boolean('include_inactive', false);
        $include_hashed_password = (bool) $request->boolean('include_hashed_password', false);

        $query = User::where('business_id', $business_id)
            ->user()
            ->where('is_cmmsn_agnt', 0)
            ->orderBy('first_name')
            ->orderBy('id');

        if (Schema::hasColumn('users', 'status') && ! $include_inactive) {
            $query->whereIn('status', ['active', 1, '1']);
        }

        $users = $query->get();
        $rows = [];
        $role_map = $this->getUserRoleMap($users->pluck('id')->all(), $business_id);
        $location_map = $this->getUserLocationExportMap($users->pluck('id')->all(), $business_id);
        $has_essentials = $this->hasAnyUserColumns([
            'essentials_salary',
            'essentials_pay_period',
            'essentials_pay_cycle',
            'essentials_sales_target',
        ]);

        foreach ($users as $user) {
            $row = [
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'contact_no' => $this->getUserContactValue($user),
                'status' => $this->normalizeStatusForExport($user->status ?? null),
                'role' => $role_map[$user->id] ?? '',
                'allow_login' => (int) ($user->allow_login ?? 0),
                'language' => $user->language,
                'address' => $this->getUserAddressValue($user),
                'created_at' => ! empty($user->created_at) ? Carbon::parse($user->created_at)->format('Y-m-d H:i:s') : null,
            ];

            if (! empty($location_map)) {
                $row['location_names'] = $location_map[$user->id] ?? '';
            }

            if (Schema::hasColumn('users', 'cmmsn_percent')) {
                $row['cmmsn_percent'] = $user->cmmsn_percent;
            }

            if ($has_essentials) {
                if (Schema::hasColumn('users', 'essentials_salary')) {
                    $row['essentials_salary'] = $user->essentials_salary;
                }
                if (Schema::hasColumn('users', 'essentials_pay_period')) {
                    $row['essentials_pay_period'] = $user->essentials_pay_period;
                }
                if (Schema::hasColumn('users', 'essentials_pay_cycle')) {
                    $row['essentials_pay_cycle'] = $user->essentials_pay_cycle;
                }
                if (Schema::hasColumn('users', 'essentials_sales_target')) {
                    $row['essentials_sales_target'] = $user->essentials_sales_target;
                }
            }

            if ($include_hashed_password && Schema::hasColumn('users', 'password')) {
                $row['password_hash'] = $user->password;
            }

            $rows[] = $row;
        }

        $filename = 'users_' . date('Ymd_His') . '.' . $format;

        Log::info('Users export', [
            'business_id' => $business_id,
            'user_id' => (int) $request->session()->get('user.id'),
            'format' => $format,
            'include_inactive' => $include_inactive,
            'include_hashed_password' => $include_hashed_password,
            'count' => count($rows),
        ]);

        if ($format === 'xlsx') {
            return Excel::download(new ArrayExport($rows), $filename);
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $heading = array_keys($rows[0] ?? [
                'surname' => null,
                'first_name' => null,
                'last_name' => null,
                'username' => null,
                'email' => null,
                'contact_no' => null,
                'status' => null,
                'role' => null,
                'allow_login' => null,
                'language' => null,
                'address' => null,
                'created_at' => null,
            ]);
            fputcsv($out, $heading);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function previewImportUsers(Request $request)
    {
        $this->authorizeUserImportExport('import');

        $request->validate([
            'file' => 'required|file|max:10240',
            'mode' => 'required|in:insert,update,upsert',
            'default_password' => 'nullable|string|min:6',
        ]);

        $business_id = (int) $request->session()->get('user.business_id');
        $rows = $this->readUserImportRows($request->file('file'));
        $required_columns = ['first_name', 'username', 'email'];
        $headings = array_keys($rows[0] ?? []);
        $missing_columns = array_values(array_diff($required_columns, $headings));

        if (! empty($missing_columns)) {
            return [
                'success' => false,
                'msg' => 'Missing required columns: ' . implode(', ', $missing_columns),
            ];
        }

        $prepared = $this->prepareUserImportPreviewData($rows, $business_id, (string) $request->input('mode'));
        $token = Str::random(40);

        Cache::put('users_import_preview:' . $token, [
            'business_id' => $business_id,
            'user_id' => (int) $request->session()->get('user.id'),
            'mode' => (string) $request->input('mode'),
            'default_password' => (string) $request->input('default_password', ''),
            'rows' => $prepared['rows'],
            'summary' => $prepared['summary'],
            'created_at' => now()->toDateTimeString(),
        ], now()->addMinutes(30));

        Log::info('Users import preview', [
            'business_id' => $business_id,
            'user_id' => (int) $request->session()->get('user.id'),
            'mode' => (string) $request->input('mode'),
            'total_rows' => $prepared['summary']['total_rows'],
            'error_rows' => $prepared['summary']['error_rows'],
            'warning_rows' => $prepared['summary']['warning_rows'],
        ]);

        return [
            'success' => true,
            'token' => $token,
            'summary' => $prepared['summary'],
            'rows' => $prepared['rows'],
        ];
    }

    public function importUsers(Request $request)
    {
        $this->authorizeUserImportExport('import');

        $request->validate([
            'token' => 'required|string',
            'mode' => 'required|in:insert,update,upsert',
            'default_password' => 'nullable|string|min:6',
        ]);

        $business_id = (int) $request->session()->get('user.business_id');
        $user_id = (int) $request->session()->get('user.id');
        $token = (string) $request->input('token');

        $payload = Cache::get('users_import_preview:' . $token);
        if (empty($payload) || (int) ($payload['business_id'] ?? 0) !== $business_id || (int) ($payload['user_id'] ?? 0) !== $user_id) {
            return ['success' => false, 'msg' => 'Import preview expired. Please preview again.'];
        }

        if (($payload['mode'] ?? '') !== (string) $request->input('mode')) {
            return ['success' => false, 'msg' => 'Import mode mismatch. Please preview again.'];
        }

        $rows = (array) ($payload['rows'] ?? []);
        $error_rows = array_filter($rows, function ($row) {
            return ! empty($row['errors']);
        });

        if (! empty($error_rows)) {
            return ['success' => false, 'msg' => 'Fix import errors in preview before confirming.'];
        }

        $default_password = (string) $request->input('default_password', ($payload['default_password'] ?? ''));
        $summary = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'warning_rows' => 0,
            'warnings' => [],
        ];

        try {
            DB::transaction(function () use ($rows, $business_id, $default_password, $request, &$summary) {
                foreach ($rows as $row) {
                    $action = (string) ($row['action'] ?? 'skip');
                    if ($action === 'skip') {
                        $summary['skipped']++;
                        continue;
                    }

                    $data = (array) ($row['data'] ?? []);
                    $warnings = [];
                    $existing = $this->findExistingImportUser((string) ($data['email'] ?? ''), (string) ($data['username'] ?? ''), (string) ($data['contact_no'] ?? ''), $business_id);
                    $user_data = $this->buildImportUserPayload($data, $business_id, $default_password, ! empty($existing));

                    if (! empty($existing)) {
                        $user = User::where('business_id', $business_id)->findOrFail($existing->id);
                        $user->fill($user_data);
                        $user->save();
                        $summary['updated']++;
                        $this->applyImportedRoleAndLocations($user, $data, $business_id, $warnings);
                        event(new UserCreatedOrModified($user, 'updated'));
                    } else {
                        $user = User::create($user_data);
                        $summary['inserted']++;
                        $this->applyImportedRoleAndLocations($user, $data, $business_id, $warnings);
                        event(new UserCreatedOrModified($user, 'added'));
                    }

                    if (! empty($warnings)) {
                        $summary['warning_rows']++;
                        $summary['warnings'][] = [
                            'email' => $data['email'] ?? '',
                            'username' => $data['username'] ?? '',
                            'warnings' => $warnings,
                        ];
                    }
                }
            });

            Cache::forget('users_import_preview:' . $token);

            Log::info('Users import confirm', [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'mode' => (string) $request->input('mode'),
                'result' => $summary,
            ]);

            return [
                'success' => true,
                'msg' => "Import completed. Inserted: {$summary['inserted']}, Updated: {$summary['updated']}, Skipped: {$summary['skipped']}",
                'data' => $summary,
            ];
        } catch (\Throwable $e) {
            Log::emergency('Users import confirm error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => $e->getMessage() ?: __('messages.something_went_wrong'),
            ];
        }
    }

    /**
     * Retrives roles array (Hides admin role from non admin users)
     *
     * @param  int  $business_id
     * @return array $roles
     */
    private function getRolesArray($business_id)
    {
        $roles_array = Role::where('business_id', $business_id)->get()->pluck('name', 'id');
        $roles = [];

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        foreach ($roles_array as $key => $value) {
            if (! $is_admin && $value == 'Admin#'.$business_id) {
                continue;
            }
            $roles[$key] = str_replace('#'.$business_id, '', $value);
        }

        return $roles;
    }

    private function authorizeUserImportExport(string $action = 'view'): void
    {
        if (! auth()->check()) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) request()->session()->get('user.business_id');
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);
        $is_superadmin = auth()->user()->can('superadmin');

        if (! ($is_admin || $is_superadmin)) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function canManageUserImportExport(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $business_id = (int) request()->session()->get('user.business_id');

        return $this->moduleUtil->is_admin(auth()->user(), $business_id) || auth()->user()->can('superadmin');
    }

    private function readUserImportRows($file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls', 'csv'], true) && class_exists(Excel::class)) {
            $sheets = Excel::toArray(new UsersImportPreview(), $file);
            $rows = $sheets[0] ?? [];
        } elseif ($extension === 'csv') {
            $rows = $this->readCsvRows($file->getRealPath());
        } else {
            throw new \RuntimeException('Invalid file format. Please upload CSV or Excel.');
        }

        $normalized = [];
        foreach ($rows as $row) {
            $row = array_change_key_case(array_map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            }, (array) $row), CASE_LOWER);

            if (empty(array_filter($row, function ($value) {
                return $value !== null && $value !== '';
            }))) {
                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    private function readCsvRows(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Unable to read CSV file.');
        }

        try {
            $headers = null;
            while (($data = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = array_map(function ($header) {
                        return strtolower(trim((string) $header));
                    }, $data);
                    continue;
                }

                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = $data[$index] ?? null;
                }
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function prepareUserImportPreviewData(array $rows, int $business_id, string $mode): array
    {
        $prepared_rows = [];
        $summary = [
            'total_rows' => count($rows),
            'new_rows' => 0,
            'matched_rows' => 0,
            'skipped_rows' => 0,
            'error_rows' => 0,
            'warning_rows' => 0,
        ];

        $roles = $this->getImportRoleMap($business_id);
        $location_names = $this->getImportLocationMap($business_id);

        $email_counts = $this->countImportIdentityValues($rows, 'email');
        $username_counts = $this->countImportIdentityValues($rows, 'username');
        $contact_counts = $this->countImportIdentityValues($rows, 'contact_no');

        foreach ($rows as $index => $row) {
            $row_number = $index + 2;
            $errors = [];
            $warnings = [];

            $row = $this->normalizeUserImportRow($row);
            if ($row['first_name'] === '') {
                $errors[] = 'first_name is required';
            }
            if ($row['username'] === '') {
                $errors[] = 'username is required';
            }
            if ($row['email'] === '') {
                $errors[] = 'email is required';
            } elseif (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'invalid email';
            }

            if ($row['email'] !== '' && ($email_counts[$row['email']] ?? 0) > 1) {
                $errors[] = 'duplicate email in file';
            }
            if ($row['username'] !== '' && ($username_counts[$row['username']] ?? 0) > 1) {
                $errors[] = 'duplicate username in file';
            }
            if ($row['contact_no'] !== '' && ($contact_counts[$row['contact_no']] ?? 0) > 1) {
                $errors[] = 'duplicate contact_no in file';
            }

            $existing = $this->findExistingImportUser($row['email'], $row['username'], $row['contact_no'], $business_id, $errors);
            $exists = ! empty($existing);

            if ($row['role'] !== '' && ! isset($roles[strtolower($row['role'])])) {
                $warnings[] = 'role not found';
            }

            $resolved_locations = $this->resolveImportedLocations($row['location_names'], $location_names, $warnings);
            $row['_resolved_locations'] = $resolved_locations;

            $status = $exists ? 'matched' : 'new';
            $action = $exists ? 'update' : 'insert';

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
                $summary['matched_rows']++;
            } else {
                $summary['new_rows']++;
            }

            if ($action === 'skip') {
                $summary['skipped_rows']++;
            }
            if (! empty($warnings)) {
                $summary['warning_rows']++;
            }

            $prepared_rows[] = [
                'row_number' => $row_number,
                'first_name' => $row['first_name'],
                'username' => $row['username'],
                'email' => $row['email'],
                'status' => $status,
                'action' => $action,
                'errors' => array_values(array_unique($errors)),
                'warnings' => array_values(array_unique($warnings)),
                'data' => $row,
            ];
        }

        return [
            'summary' => $summary,
            'rows' => $prepared_rows,
        ];
    }

    private function normalizeUserImportRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[strtolower(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        foreach (['surname', 'first_name', 'last_name', 'username', 'email', 'contact_no', 'password', 'status', 'role', 'allow_login', 'language', 'address', 'location_names', 'password_hash'] as $field) {
            if (! array_key_exists($field, $normalized)) {
                $normalized[$field] = '';
            }
        }

        return $normalized;
    }

    private function countImportIdentityValues(array $rows, string $key): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    private function findExistingImportUser(string $email, string $username, string $contact_no, int $business_id, array &$errors = [])
    {
        $matches = collect();

        if ($email !== '' && Schema::hasColumn('users', 'email')) {
            $user = User::where('business_id', $business_id)->where('email', $email)->first();
            if (! empty($user)) {
                $matches->push($user);
            }
        }

        if ($username !== '' && Schema::hasColumn('users', 'username')) {
            $user = User::where('business_id', $business_id)->where('username', $username)->first();
            if (! empty($user) && ! $matches->contains('id', $user->id)) {
                $matches->push($user);
            }
        }

        if ($contact_no !== '') {
            $contact_query = User::where('business_id', $business_id);
            $has_contact_query = false;
            $contact_query->where(function ($query) use ($contact_no, &$has_contact_query) {
                if (Schema::hasColumn('users', 'contact_no')) {
                    $query->orWhere('contact_no', $contact_no);
                    $has_contact_query = true;
                }
                if (Schema::hasColumn('users', 'contact_number')) {
                    $query->orWhere('contact_number', $contact_no);
                    $has_contact_query = true;
                }
            });

            if ($has_contact_query) {
                $user = $contact_query->first();
                if (! empty($user) && ! $matches->contains('id', $user->id)) {
                    $matches->push($user);
                }
            }
        }

        if ($matches->count() > 1) {
            $errors[] = 'email / username / contact_no matched different existing users';

            return null;
        }

        return $matches->first();
    }

    private function getImportRoleMap(int $business_id): array
    {
        $roles = [];
        foreach (Role::where('business_id', $business_id)->get(['id', 'name']) as $role) {
            $display_name = str_replace('#' . $business_id, '', (string) $role->name);
            $roles[strtolower($display_name)] = $role;
            $roles[strtolower((string) $role->name)] = $role;
        }

        return $roles;
    }

    private function getImportLocationMap(int $business_id): array
    {
        $locations = [];
        foreach (BusinessLocation::where('business_id', $business_id)->get(['id', 'name']) as $location) {
            $locations[strtolower((string) $location->name)] = $location;
        }

        return $locations;
    }

    private function resolveImportedLocations(string $location_names, array $location_map, array &$warnings = []): array
    {
        $location_names = trim($location_names);
        if ($location_names === '') {
            return [
                'all' => false,
                'ids' => [],
            ];
        }

        if (strtolower($location_names) === 'all') {
            return [
                'all' => true,
                'ids' => [],
            ];
        }

        $ids = [];
        $names = preg_split('/[,;|]+/', $location_names) ?: [];
        foreach ($names as $name) {
            $key = strtolower(trim($name));
            if ($key === '') {
                continue;
            }
            if (! isset($location_map[$key])) {
                $warnings[] = 'location not found: ' . trim($name);
                continue;
            }
            $ids[] = $location_map[$key]->id;
        }

        return [
            'all' => false,
            'ids' => array_values(array_unique($ids)),
        ];
    }

    private function buildImportUserPayload(array $data, int $business_id, string $default_password, bool $is_update): array
    {
        $columns = Schema::getColumnListing('users');
        $payload = [];

        $payload['business_id'] = $business_id;
        if (in_array('user_type', $columns, true)) {
            $payload['user_type'] = 'user';
        }
        if (in_array('is_cmmsn_agnt', $columns, true)) {
            $payload['is_cmmsn_agnt'] = 0;
        }

        $basic_fields = ['surname', 'first_name', 'last_name', 'username', 'email', 'language'];
        foreach ($basic_fields as $field) {
            if (in_array($field, $columns, true) && array_key_exists($field, $data)) {
                $payload[$field] = $this->nullableString($data[$field]);
            }
        }

        $status = $this->parseImportedStatus($data['status'] ?? null);
        if (in_array('status', $columns, true)) {
            $payload['status'] = $status;
        }

        $allow_login = $this->parseImportedAllowLogin($data['allow_login'] ?? null);
        if (in_array('allow_login', $columns, true)) {
            $payload['allow_login'] = $allow_login;
        }

        $contact_no = $this->nullableString($data['contact_no'] ?? null);
        if (in_array('contact_no', $columns, true)) {
            $payload['contact_no'] = $contact_no;
        }
        if (in_array('contact_number', $columns, true) && $contact_no !== null) {
            $payload['contact_number'] = $contact_no;
        }

        $address = $this->nullableString($data['address'] ?? null);
        if (in_array('address', $columns, true)) {
            $payload['address'] = $address;
        }
        if (in_array('current_address', $columns, true) && $address !== null) {
            $payload['current_address'] = $address;
        }
        if (in_array('permanent_address', $columns, true) && $address !== null) {
            $payload['permanent_address'] = $address;
        }

        if (Schema::hasColumn('users', 'cmmsn_percent') && isset($data['cmmsn_percent']) && $data['cmmsn_percent'] !== '') {
            $payload['cmmsn_percent'] = $this->commonUtil->num_uf($data['cmmsn_percent']);
        }
        foreach (['essentials_salary', 'essentials_pay_period', 'essentials_pay_cycle', 'essentials_sales_target'] as $field) {
            if (in_array($field, $columns, true) && isset($data[$field]) && $data[$field] !== '') {
                $payload[$field] = $data[$field];
            }
        }

        if ($allow_login) {
            $plain_password = trim((string) ($data['password'] ?? ''));
            $password_hash = trim((string) ($data['password_hash'] ?? ''));
            if ($plain_password !== '') {
                $payload['password'] = Hash::make($plain_password);
            } elseif ($password_hash !== '') {
                $payload['password'] = $password_hash;
            } elseif (! $is_update) {
                $generated = $default_password !== '' ? $default_password : Str::random(10);
                $payload['password'] = Hash::make($generated);
            }
        } elseif (in_array('password', $columns, true) && ! $is_update) {
            $payload['password'] = null;
            $payload['username'] = null;
        }

        if ($is_update && empty($payload['password'])) {
            unset($payload['password']);
        }

        if (! in_array('created_at', $columns, true)) {
            unset($payload['created_at']);
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    private function applyImportedRoleAndLocations(User $user, array $data, int $business_id, array &$warnings = []): void
    {
        $this->syncImportedRole($user, (string) ($data['role'] ?? ''), $business_id, $warnings);
        $resolved_locations = $data['_resolved_locations'] ?? $this->resolveImportedLocations((string) ($data['location_names'] ?? ''), $this->getImportLocationMap($business_id), $warnings);
        $this->syncImportedLocations($user, (array) $resolved_locations, $business_id);
    }

    private function syncImportedRole(User $user, string $role_name, int $business_id, array &$warnings = []): void
    {
        $role_name = trim($role_name);
        if ($role_name === '') {
            return;
        }

        $roles = $this->getImportRoleMap($business_id);
        $role = $roles[strtolower($role_name)] ?? null;
        if (empty($role)) {
            $warnings[] = 'role not found';

            return;
        }

        $this->commonUtil->revokeLocationPermissionsFromRole($role);
        $user->syncRoles([$role->name]);
    }

    private function syncImportedLocations(User $user, array $resolved_locations, int $business_id): void
    {
        $revoke = [];
        $all_locations = BusinessLocation::where('business_id', $business_id)->get(['id']);
        foreach ($all_locations as $location) {
            $revoke[] = 'location.' . $location->id;
        }
        $revoke[] = 'access_all_locations';

        $current_permissions = array_values(array_filter($revoke, function ($permission) use ($user) {
            return $user->hasDirectPermission($permission);
        }));
        if (! empty($current_permissions)) {
            $user->revokePermissionTo($current_permissions);
        }

        if (! empty($resolved_locations['all'])) {
            $user->givePermissionTo('access_all_locations');

            return;
        }

        $permissions = [];
        foreach ((array) ($resolved_locations['ids'] ?? []) as $location_id) {
            $permissions[] = 'location.' . $location_id;
        }

        if (! empty($permissions)) {
            $user->givePermissionTo($permissions);
        }
    }

    private function parseImportedStatus($value): string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || in_array($value, ['1', 'active', 'yes', 'true'], true)) {
            return 'active';
        }

        return 'inactive';
    }

    private function parseImportedAllowLogin($value): int
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || in_array($value, ['1', 'yes', 'true', 'active'], true)) {
            return 1;
        }

        return 0;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function getUserRoleMap(array $user_ids, int $business_id): array
    {
        if (empty($user_ids)) {
            return [];
        }

        $user_model = config('permission.models.user') ?? User::class;
        $rows = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', $user_model)
            ->whereIn('model_has_roles.model_id', $user_ids)
            ->orderBy('roles.id')
            ->get([
                'model_has_roles.model_id as user_id',
                'roles.name as role_name',
            ]);

        if (Schema::hasColumn('roles', 'business_id')) {
            $rows = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', $user_model)
                ->whereIn('model_has_roles.model_id', $user_ids)
                ->where(function ($query) use ($business_id) {
                    $query->whereNull('roles.business_id')->orWhere('roles.business_id', $business_id);
                })
                ->orderBy('roles.id')
                ->get([
                    'model_has_roles.model_id as user_id',
                    'roles.name as role_name',
                ]);
        }

        $map = [];
        foreach ($rows as $row) {
            if (! isset($map[$row->user_id])) {
                $map[$row->user_id] = str_replace('#' . $business_id, '', (string) $row->role_name);
            }
        }

        return $map;
    }

    private function getUserLocationExportMap(array $user_ids, int $business_id): array
    {
        if (empty($user_ids)) {
            return [];
        }

        $user_model = config('permission.models.user') ?? User::class;
        $rows = DB::table('model_has_permissions')
            ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_type', $user_model)
            ->whereIn('model_has_permissions.model_id', $user_ids)
            ->get([
                'model_has_permissions.model_id as user_id',
                'permissions.name as permission_name',
            ]);

        $location_names_by_id = BusinessLocation::where('business_id', $business_id)
            ->pluck('name', 'id')
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $permission = (string) $row->permission_name;
            if ($permission === 'access_all_locations') {
                $map[$row->user_id] = 'ALL';
                continue;
            }
            if (Str::startsWith($permission, 'location.') && (! isset($map[$row->user_id]) || $map[$row->user_id] !== 'ALL')) {
                $location_id = (int) Str::after($permission, 'location.');
                if (isset($location_names_by_id[$location_id])) {
                    $map[$row->user_id][] = $location_names_by_id[$location_id];
                }
            }
        }

        foreach ($map as $user_id => $value) {
            if (is_array($value)) {
                $map[$user_id] = implode(', ', array_unique($value));
            }
        }

        return $map;
    }

    private function getUserContactValue(User $user): ?string
    {
        if (Schema::hasColumn('users', 'contact_no') && ! empty($user->contact_no)) {
            return $user->contact_no;
        }

        if (Schema::hasColumn('users', 'contact_number') && ! empty($user->contact_number)) {
            return $user->contact_number;
        }

        return null;
    }

    private function getUserAddressValue(User $user): ?string
    {
        if (Schema::hasColumn('users', 'address') && ! empty($user->address)) {
            return $user->address;
        }
        if (Schema::hasColumn('users', 'current_address') && ! empty($user->current_address)) {
            return $user->current_address;
        }
        if (Schema::hasColumn('users', 'permanent_address') && ! empty($user->permanent_address)) {
            return $user->permanent_address;
        }

        return null;
    }

    private function normalizeStatusForExport($status): string
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, ['1', 'active', 'yes', 'true'], true) ? 'active' : 'inactive';
    }

    private function hasAnyUserColumns(array $columns): bool
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn('users', $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Signes in from user id
     *
     * @param  int  $id
     */
    public function signInAsUser($id)
    {
        if (! auth()->user()->can('superadmin') && empty(session('previous_user_id'))) {
            abort(403, 'Unauthorized action.');
        }

        $user_id = auth()->user()->id;
        $username = auth()->user()->username;
        session()->flush();

        if (request()->has('save_current')) {
            session(['previous_user_id' => $user_id, 'previous_username' => $username]);
        }

        Auth::loginUsingId($id);

        return redirect()->route('home');
    }
}
