<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Jobs\ProcessInboundTelegramUpdateJob;
use Modules\LoanManagement\Services\TelegramSettingsService;

class LoanTelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $expected = TelegramSettingsService::webhookSecret();
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        ProcessInboundTelegramUpdateJob::dispatch($request->all());

        return response()->json(['ok' => true]);
    }
}
