<?php

namespace Modules\LoanManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\TelegramAuthService;

class TelegramAuthController extends Controller
{
    public function __construct(
        protected TelegramAuthService $telegramAuth,
    ) {
    }

    public function init()
    {
        $data = $this->telegramAuth->initSession();

        return response()->json([
            'success' => true,
            'message' => 'Telegram auth session initialized',
            'data' => $data,
        ]);
    }

    public function status(string $token)
    {
        $data = $this->telegramAuth->checkStatus($token);

        return response()->json([
            'success' => true,
            'message' => 'Telegram auth status',
            'data' => $data,
        ]);
    }

    public function webhook(Request $request)
    {
        $expected = $this->telegramAuth->resolveWebhookSecret();
        $provided = (string)$request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            abort(403, 'Invalid Telegram webhook secret.');
        }

        $this->telegramAuth->handleWebhook($request->all());

        return response()->json(['ok' => true]);
    }
}
