<?php

namespace Modules\ClearDataByDate\Http\Controllers;

use App\BusinessLocation;
use App\Http\Controllers\Controller;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\ClearDataByDate\Entities\ClearDataLog;
use Modules\ClearDataByDate\Http\Requests\ClearDataByDateDeleteRequest;
use Modules\ClearDataByDate\Http\Requests\ClearDataByDatePreviewRequest;
use Modules\ClearDataByDate\Services\ClearDataByDateService;

class ClearDataByDateController extends Controller
{
    public function __construct(
        protected Util $commonUtil,
        protected ClearDataByDateService $service
    ) {
    }

    public function index(Request $request)
    {
        if (! auth()->user()->can('clear_data_by_date.access') && ! auth()->user()->can('clear_data_by_date.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($businessId, true);

        $token = (string) $request->query('preview_token', '');
        $preview_counts = null;
        $location_id = null;
        $modules = [];
        $start_date_input = '';
        $end_date_input = '';

        if ($token !== '') {
            $preview = $request->session()->get('clear_data_by_date.preview.'.$token);
            if (! empty($preview) && (int) ($preview['business_id'] ?? 0) === $businessId) {
                $preview_counts = $preview['preview_counts'] ?? null;
                $location_id = $preview['location_id'] ?? null;
                $modules = $preview['modules'] ?? [];
                $start_date_input = $preview['start_date_input'] ?? '';
                $end_date_input = $preview['end_date_input'] ?? '';
            }
        }

        return view('clear_data_by_date::index', compact(
            'business_locations',
            'preview_counts',
            'token',
            'location_id',
            'modules',
            'start_date_input',
            'end_date_input'
        ));
    }

    public function preview(ClearDataByDatePreviewRequest $request)
    {
        if (! auth()->user()->can('clear_data_by_date.access') && ! auth()->user()->can('clear_data_by_date.view')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->commonUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $userId = (int) $request->session()->get('user.id');

        $modules = array_values(array_intersect((array) $request->input('modules', []), $this->service->allowedModules()));
        if (empty($modules)) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ])->withInput();
        }

        $start_date_input = (string) $request->input('start_date');
        $end_date_input = (string) $request->input('end_date');

        $start_date = Carbon::parse($this->commonUtil->uf_date($start_date_input))->format('Y-m-d');
        $end_date = Carbon::parse($this->commonUtil->uf_date($end_date_input))->format('Y-m-d');
        if ($end_date < $start_date) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ])->withInput();
        }

        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        $previewCounts = $this->service->previewCounts($businessId, $start_date, $end_date, $locationId, $modules);

        $log = ClearDataLog::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'date_from' => $start_date,
            'date_to' => $end_date,
            'location_id' => $locationId,
            'selected_modules' => $modules,
            'preview_counts' => $previewCounts,
            'total_deleted' => null,
            'status' => 'previewed',
            'error_message' => null,
        ]);

        $token = (string) Str::uuid();
        $request->session()->put('clear_data_by_date.preview.'.$token, [
            'token' => $token,
            'business_id' => $businessId,
            'user_id' => $userId,
            'date_from' => $start_date,
            'date_to' => $end_date,
            'start_date_input' => $start_date_input,
            'end_date_input' => $end_date_input,
            'location_id' => $locationId,
            'modules' => $modules,
            'preview_counts' => $previewCounts,
            'log_id' => $log->id,
            'created_at' => Carbon::now()->toDateTimeString(),
        ]);

        return redirect()->route('clear_data_by_date.index', ['preview_token' => $token]);
    }

    public function destroy(ClearDataByDateDeleteRequest $request)
    {
        if (! auth()->user()->can('clear_data_by_date.access') && ! auth()->user()->can('clear_data_by_date.view')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->commonUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $userId = (int) $request->session()->get('user.id');

        if (strtoupper(trim((string) $request->input('confirm_text'))) !== 'DELETE') {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Confirmation text mismatch.',
            ]);
        }

        if (! Hash::check((string) $request->input('password'), (string) auth()->user()->password)) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Invalid password.',
            ]);
        }

        $token = (string) $request->input('preview_token');
        $preview = $request->session()->get('clear_data_by_date.preview.'.$token);
        if (empty($preview) || (int) ($preview['business_id'] ?? 0) !== $businessId) {
            return redirect()->action([self::class, 'index'])->with('status', [
                'success' => 0,
                'msg' => 'Preview session is missing/expired. Please click "Preview" again.',
            ]);
        }

        $log = ! empty($preview['log_id']) ? ClearDataLog::find($preview['log_id']) : null;
        if ($log) {
            $log->status = 'in_progress';
            $log->save();
        }

        $dryRun = (bool) $request->boolean('dry_run', false);
        $continueOnBlocked = (bool) $request->boolean('continue_on_blocked', true);

        try {
            if ($dryRun) {
                if ($log) {
                    $log->status = 'dry_run';
                    $log->save();
                }

                return redirect()->route('clear_data_by_date.index', ['preview_token' => $token])
                    ->with('status', ['success' => 1, 'msg' => 'Dry-run logged. No data deleted.']);
            }

            $deleted = DB::transaction(function () use ($preview, $businessId, $userId, $continueOnBlocked) {
                return $this->service->deleteSelectedData(
                    $businessId,
                    $userId,
                    (string) $preview['date_from'],
                    (string) $preview['date_to'],
                    $preview['location_id'] ?? null,
                    (array) $preview['modules'],
                    (array) ($preview['preview_counts'] ?? []),
                    null,
                    $continueOnBlocked
                );
            });

            if ($log) {
                $log->total_deleted = $deleted;
                $log->status = 'completed';
                $log->save();
            }

            $request->session()->forget('clear_data_by_date.preview.'.$token);

            return redirect()->action([self::class, 'index'])->with('status', [
                'success' => 1,
                'msg' => 'Deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            if ($log) {
                $log->status = 'failed';
                $log->error_message = $e->getMessage();
                $log->save();
            }

            \Log::emergency('ClearDataByDate delete failed: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('clear_data_by_date.index', ['preview_token' => $token])
                ->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        }
    }
}

