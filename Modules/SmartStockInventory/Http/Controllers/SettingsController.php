<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartStockSetting;
use Modules\SmartStockInventory\Models\SmartStockActionLog;
use Modules\SmartStockInventory\Services\TelegramAlertService;

class SettingsController extends BaseSmartStockController
{
    public function __construct(\App\Utils\Util $util, private TelegramAlertService $telegram)
    {
        parent::__construct($util);
    }
    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.update'), 403);

        $setting = SmartStockSetting::firstOrCreate(
            ['business_id' => $this->businessId()],
            ['telegram_enabled' => 0, 'telegram_bot_token' => '', 'telegram_chat_id' => '']
        );

        return view('smartstockinventory::settings.index', compact('setting'));
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.settings'), 403);

        $data = $request->validate([
            'telegram_enabled' => 'nullable|boolean',
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_chat_id' => 'nullable|string|max:255',
            'allow_negative_adjustment' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'blind_count_default' => 'nullable|boolean',
            'freeze_sell_during_count' => 'nullable|boolean',
            'mismatch_threshold' => 'nullable|numeric',
            'recount_threshold' => 'nullable|numeric',
            'auto_generate_adjustment' => 'nullable|boolean',
            'auto_close_session' => 'nullable|boolean',
            'require_imei_validation' => 'nullable|boolean',
            'enable_super_admin_override' => 'nullable|boolean',
            'reason' => 'required|string|max:500',
        ]);

        $setting = SmartStockSetting::firstOrCreate(['business_id' => $this->businessId()]);
        $old = $setting->toArray();
        $setting->fill([
            'telegram_enabled' => (int) ($data['telegram_enabled'] ?? 0),
            'telegram_bot_token' => $data['telegram_bot_token'] ?? '',
            'telegram_chat_id' => $data['telegram_chat_id'] ?? '',
            'allow_negative_adjustment' => (int) ($data['allow_negative_adjustment'] ?? 0),
            'require_approval' => (int) ($data['require_approval'] ?? 0),
            'blind_count_default' => (int) ($data['blind_count_default'] ?? 0),
            'freeze_sell_during_count' => (int) ($data['freeze_sell_during_count'] ?? 0),
            'mismatch_threshold' => (float) ($data['mismatch_threshold'] ?? 0),
            'recount_threshold' => (float) ($data['recount_threshold'] ?? 5),
            'auto_generate_adjustment' => (int) ($data['auto_generate_adjustment'] ?? 0),
            'auto_close_session' => (int) ($data['auto_close_session'] ?? 0),
            'require_imei_validation' => (int) ($data['require_imei_validation'] ?? 0),
            'enable_super_admin_override' => (int) ($data['enable_super_admin_override'] ?? 1),
            'updated_by' => auth()->id(),
        ])->save();

        config()->set('smartstockinventory.telegram.enabled', (bool) $setting->telegram_enabled);
        config()->set('smartstockinventory.telegram.bot_token', $setting->telegram_bot_token);
        config()->set('smartstockinventory.telegram.chat_id', $setting->telegram_chat_id);
        $userName = trim((string) ((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')));
        if ($userName === '') { $userName = (string) (auth()->user()->username ?? ''); }
        SmartStockActionLog::create([
            'user_id' => auth()->id(),
            'user_name' => $userName,
            'business_id' => $this->businessId(),
            'module_name' => 'SmartStockInventory',
            'table_name' => 'smart_stock_settings',
            'record_id' => $setting->id,
            'location_id' => null,
            'action_type' => 'update_telegram_settings',
            'reference_type' => 'smart_stock_setting',
            'reference_id' => $setting->id,
            'old_data' => json_encode($old),
            'new_data' => json_encode($setting->toArray()),
            'reason' => $data['reason'],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', ['success' => 1, 'msg' => 'Settings saved']);
    }

    public function testTelegram(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.settings'), 403);
        $this->telegram->send('SmartStockInventory test message', ['business_id' => $this->businessId(), 'user_id' => auth()->id()]);
        return back()->with('status', ['success' => 1, 'msg' => 'Telegram test sent']);
    }

    public function resetDefault(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.settings'), 403);
        $setting = SmartStockSetting::firstOrCreate(['business_id' => $this->businessId()]);
        $old = $setting->toArray();
        $setting->fill([
            'telegram_enabled' => 0, 'telegram_bot_token' => '', 'telegram_chat_id' => '',
            'allow_negative_adjustment' => 0, 'require_approval' => 1, 'blind_count_default' => 0,
            'freeze_sell_during_count' => 0, 'mismatch_threshold' => 0, 'recount_threshold' => 5,
            'auto_generate_adjustment' => 0, 'auto_close_session' => 0, 'require_imei_validation' => 0,
        ])->save();
        $userName = trim((string) ((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')));
        if ($userName === '') { $userName = (string) (auth()->user()->username ?? ''); }
        SmartStockActionLog::create(['user_id' => auth()->id(), 'user_name' => $userName, 'business_id' => $this->businessId(), 'module_name' => 'SmartStockInventory', 'table_name' => 'smart_stock_settings', 'record_id' => $setting->id, 'action_type' => 'reset_settings_default', 'reference_type' => 'smart_stock_setting', 'reference_id' => $setting->id, 'old_data' => json_encode($old), 'new_data' => json_encode($setting->toArray()), 'reason' => 'reset_default', 'ip_address' => $request->ip()]);
        return back()->with('status', ['success' => 1, 'msg' => 'Settings reset']);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.export'), 403);
        $setting = SmartStockSetting::firstOrCreate(['business_id' => $this->businessId()]);
        return Excel::download(new ArrayExport([['key' => 'settings', 'value' => json_encode($setting->toArray())]]), 'smart_stock_settings_' . now()->format('Ymd_His') . '.xlsx');
    }
}
