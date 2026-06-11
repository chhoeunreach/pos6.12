<?php

namespace Modules\NotificationCenter\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\NotificationCenter\Entities\NotificationSetting;

class NotificationSettingController extends Controller
{
    public function index()
    {
        $settings = [
            'telegram_bot_token' => NotificationSetting::getValue('telegram_bot_token', config('notificationcenter.telegram_bot_token')),
            'queue_enabled' => NotificationSetting::getValue('queue_enabled', config('notificationcenter.queue_enabled')),
            'pdf_engine' => NotificationSetting::getValue('pdf_engine', config('notificationcenter.pdf_engine')),
            'cleanup_days' => NotificationSetting::getValue('cleanup_days', config('notificationcenter.cleanup_days')),
            'retry_attempts' => NotificationSetting::getValue('retry_attempts', config('notificationcenter.retry_attempts')),
        ];

        return view('notificationcenter::settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'telegram_bot_token' => 'nullable|string',
            'queue_enabled' => 'boolean',
            'pdf_engine' => 'nullable|string|max:50',
            'cleanup_days' => 'nullable|integer|min:1|max:90',
            'retry_attempts' => 'nullable|integer|min:0|max:10',
        ]);

        foreach ($data as $key => $value) {
            NotificationSetting::setValue($key, (string) $value);
        }

        return redirect()->route('notificationcenter.settings.index')
            ->with('status', ['success' => true, 'msg' => __('lang_v1.success')]);
    }
}
