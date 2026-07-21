<?php

namespace Modules\NotificationCenter\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\BusinessLocation;
use App\Exports\ArrayExport;
use Modules\NotificationCenter\Entities\NotificationGroup;
use Modules\NotificationCenter\Imports\NotificationGroupsImportPreview;
use Modules\NotificationCenter\Services\TelegramService;

class NotificationGroupController extends Controller
{
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $fromGroups = NotificationGroup::where(function ($query) use ($business_id) {
                $query->where('business_id', $business_id)
                    ->orWhereNull('business_id');
            })
            ->where(function ($query) {
                $query->where('direction', 'from')
                    ->orWhereNull('direction');
            })
            ->latest()
            ->get();

        $toGroups = NotificationGroup::where(function ($query) use ($business_id) {
                $query->where('business_id', $business_id)
                    ->orWhereNull('business_id');
            })
            ->where('direction', 'to')
            ->latest()
            ->get();

        return view('notificationcenter::groups.index', compact('fromGroups', 'toGroups'));
    }

    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('notificationcenter::groups.create', compact('business_locations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'chat_id' => 'required|string|max:255',
            'module_type' => 'required|string|max:100',
            'direction' => 'nullable|string|in:from,to',
            'location_id' => 'nullable|integer',
            'send_text' => 'boolean',
            'send_pdf' => 'boolean',
            'active' => 'boolean',
        ]);

        $data['business_id'] = $request->session()->get('user.business_id');
        $data['created_by'] = $request->user()->id;
        $data['send_text'] = $request->boolean('send_text', true);
        $data['send_pdf'] = $request->boolean('send_pdf', true);
        $data['active'] = $request->boolean('active', true);
        $data['location_name'] = $this->locationNameFor($data['location_id'] ?? null, $data['business_id']);

        NotificationGroup::create($data);

        return redirect()->route('notificationcenter.groups.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function edit($id)
    {
        $group = NotificationGroup::findOrFail($id);
        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('notificationcenter::groups.edit', compact('group', 'business_locations'));
    }

    public function update(Request $request, $id)
    {
        $group = NotificationGroup::findOrFail($id);
        $business_id = $request->session()->get('user.business_id');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'chat_id' => 'required|string|max:255',
            'module_type' => 'required|string|max:100',
            'direction' => 'nullable|string|in:from,to',
            'location_id' => 'nullable|integer',
            'send_text' => 'boolean',
            'send_pdf' => 'boolean',
            'active' => 'boolean',
        ]);

        $data['send_text'] = $request->boolean('send_text', true);
        $data['send_pdf'] = $request->boolean('send_pdf', true);
        $data['active'] = $request->boolean('active', true);
        $data['business_id'] = $group->business_id ?: $business_id;
        $data['location_name'] = $this->locationNameFor($data['location_id'] ?? null, $data['business_id']);

        $group->update($data);

        return redirect()->route('notificationcenter.groups.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }

    public function destroy($id)
    {
        NotificationGroup::findOrFail($id)->delete();

        return redirect()->route('notificationcenter.groups.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.deleted_success')]);
    }

    public function downloadTemplate()
    {
        $columns = [
            'name',
            'chat_id',
            'module_type',
            'direction',
            'location_id',
            'location_name',
            'send_text',
            'send_pdf',
            'active',
        ];

        $filename = 'notification_groups_template_' . date('Ymd_His') . '.csv';

        $callback = function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    public function export(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $include_inactive = $request->boolean('include_inactive', false);
        $format = strtolower((string) $request->input('format', 'csv'));
        $format = in_array($format, ['csv', 'xlsx'], true) ? $format : 'csv';

        $groups = NotificationGroup::where('business_id', $business_id)
            ->when(!$include_inactive, fn($q) => $q->where('active', 1))
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($groups as $g) {
            $rows[] = [
                'name' => $g->name,
                'chat_id' => $g->chat_id,
                'module_type' => $g->module_type,
                'direction' => $g->direction,
                'location_id' => $g->location_id,
                'location_name' => $g->location_name,
                'send_text' => $g->send_text ? 'Yes' : 'No',
                'send_pdf' => $g->send_pdf ? 'Yes' : 'No',
                'active' => $g->active ? 'Yes' : 'No',
            ];
        }

        $filename = 'notification_groups_' . date('Ymd_His') . '.' . $format;

        if ($format === 'xlsx') {
            return Excel::download(new ArrayExport($rows), $filename);
        }

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys($rows[0] ?? [
                'name' => null, 'chat_id' => null, 'module_type' => null,
                'direction' => null, 'location_id' => null, 'location_name' => null,
                'send_text' => null, 'send_pdf' => null, 'active' => null,
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
        $request->validate([
            'file' => 'required|file|max:5120',
            'mode' => 'required|in:insert,update,upsert',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');
        $mode = $request->input('mode');

        try {
            $file = $request->file('file');
            $sheets = Excel::toArray(new NotificationGroupsImportPreview, $file);
            $rawRows = $sheets[0] ?? [];

            $normalizedRows = [];
            $rowNumber = 1;
            foreach ($rawRows as $row) {
                $rowNumber++;
                $row = array_change_key_case(array_map(fn($v) => is_string($v) ? trim($v) : $v, $row), CASE_LOWER);
                if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                    continue;
                }
                $normalizedRows[] = [
                    'row_number' => $rowNumber,
                    'data' => $row,
                ];
            }

            $existingByName = NotificationGroup::where('business_id', $business_id)
                ->whereIn('name', array_values(array_unique(array_filter(array_column(array_column($normalizedRows, 'data'), 'name')))))
                ->get()
                ->keyBy('name');

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
                $chatId = trim((string) ($row['chat_id'] ?? ''));
                $moduleType = trim((string) ($row['module_type'] ?? ''));

                if ($name === '') {
                    $errors[] = 'name is required';
                }
                if ($chatId === '') {
                    $errors[] = 'chat_id is required';
                }
                if ($moduleType === '') {
                    $errors[] = 'module_type is required';
                }

                $sendText = $this->parseBoolean($row['send_text'] ?? null);
                $sendPdf = $this->parseBoolean($row['send_pdf'] ?? null);
                $active = $this->parseBoolean($row['active'] ?? null);

                $direction = strtolower(trim((string) ($row['direction'] ?? '')));
                if ($direction !== '' && !in_array($direction, ['from', 'to'])) {
                    $errors[] = 'direction must be from or to';
                }

                $existing = $existingByName->get($name);
                $exists = !empty($existing);
                $status = 'new';
                $action = 'insert';

                if ($exists) {
                    $status = 'existing';
                    $action = 'update';
                }

                if ($mode === 'insert' && $exists) {
                    $action = 'skip';
                } elseif ($mode === 'update' && !$exists) {
                    $action = 'skip';
                }

                if (!empty($errors)) {
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
                    'chat_id' => $chatId,
                    'module_type' => $moduleType,
                    'status' => $status,
                    'action' => $action,
                    'errors' => $errors,
                    'data' => $row,
                    'parsed' => [
                        'send_text' => $sendText,
                        'send_pdf' => $sendPdf,
                        'active' => $active,
                        'direction' => $direction ?: null,
                    ],
                ];
            }

            $token = Str::random(40);
            Cache::put('ng_import_preview:' . $token, [
                'business_id' => $business_id,
                'user_id' => $user_id,
                'mode' => $mode,
                'rows' => $previewRows,
                'created_at' => now()->toDateTimeString(),
            ], now()->addMinutes(30));

            return [
                'success' => true,
                'token' => $token,
                'summary' => $summary,
                'rows' => $previewRows,
            ];
        } catch (\Exception $e) {
            \Log::emergency('NG import preview error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    }

    public function importConfirm(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'mode' => 'required|in:insert,update,upsert',
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');
        $token = $request->input('token');
        $requestedMode = $request->input('mode');

        $payload = Cache::get('ng_import_preview:' . $token);
        if (empty($payload) || ($payload['business_id'] ?? null) !== $business_id || ($payload['user_id'] ?? null) !== $user_id) {
            return ['success' => false, 'msg' => 'Import preview expired. Please preview again.'];
        }

        $mode = $payload['mode'] ?? null;
        if (!in_array($mode, ['insert', 'update', 'upsert'], true)) {
            return ['success' => false, 'msg' => 'Import preview expired. Please preview again.'];
        }

        $rows = $payload['rows'] ?? [];
        $errorRows = array_filter($rows, fn($r) => ($r['status'] ?? '') === 'error');
        if (!empty($errorRows)) {
            return ['success' => false, 'msg' => 'Fix import errors in preview before confirming.'];
        }

        try {
            $result = DB::transaction(function () use ($rows, $business_id, $user_id, $mode) {
                $updated = 0;
                $inserted = 0;
                $skipped = 0;

                $existingGroups = NotificationGroup::where('business_id', $business_id)->get()->keyBy('name');

                foreach ($rows as $row) {
                    $action = $row['action'] ?? 'skip';
                    if ($action === 'skip') {
                        $skipped++;
                        continue;
                    }

                    $data = $row['data'] ?? [];
                    $parsed = $row['parsed'] ?? [];
                    $name = trim((string) ($data['name'] ?? ''));
                    if ($name === '') {
                        throw new \Exception('Invalid row: name is required.');
                    }

                    $input = [
                        'business_id' => $business_id,
                        'name' => $name,
                        'chat_id' => trim((string) ($data['chat_id'] ?? '')),
                        'module_type' => trim((string) ($data['module_type'] ?? '')),
                        'direction' => $parsed['direction'] ?? null,
                        'location_id' => $data['location_id'] !== '' && $data['location_id'] !== null ? $data['location_id'] : null,
                        'location_name' => $data['location_name'] ?? null,
                        'send_text' => $parsed['send_text'] ?? true,
                        'send_pdf' => $parsed['send_pdf'] ?? true,
                        'active' => $parsed['active'] ?? true,
                    ];

                    $existing = $existingGroups->get($name);

                    if ($existing) {
                        if ($mode === 'insert') {
                            $skipped++;
                            continue;
                        }
                        $existing->fill($input);
                        $existing->save();
                        $existingGroups->put($name, $existing);
                        $updated++;
                    } else {
                        if ($mode === 'update') {
                            $skipped++;
                            continue;
                        }
                        $input['created_by'] = $user_id;
                        NotificationGroup::create($input);
                        $inserted++;
                    }
                }

                return compact('inserted', 'updated', 'skipped');
            });

            Cache::forget('ng_import_preview:' . $token);

            return [
                'success' => true,
                'msg' => "Import completed. Inserted: {$result['inserted']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}",
                'data' => $result,
            ];
        } catch (\Exception $e) {
            \Log::emergency('NG import confirm error File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            return [
                'success' => false,
                'msg' => $e->getMessage() ?: __('messages.something_went_wrong'),
            ];
        }
    }

    private function parseBoolean($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        $v = strtolower(trim((string) $value));
        return in_array($v, ['1', 'yes', 'true', 'active']);
    }

    private function locationNameFor($locationId, $businessId): ?string
    {
        if (empty($locationId)) {
            return null;
        }

        return BusinessLocation::where('business_id', $businessId)
            ->where('id', $locationId)
            ->value('name');
    }

    public function test($id, TelegramService $telegram)
    {
        $group = NotificationGroup::findOrFail($id);
        $result = $telegram->sendText($group->chat_id, 'Test message from Notification Center');

        if ($result['success']) {
            return redirect()->back()
                ->with('status', ['success' => true, 'msg' => 'Test message sent']);
        }

        return redirect()->back()
            ->with('status', ['success' => false, 'msg' => 'Test failed: '.($result['error'] ?? 'Unknown error')]);
    }
}
