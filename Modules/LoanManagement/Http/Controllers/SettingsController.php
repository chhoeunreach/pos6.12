<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\BusinessLocation;
use App\Business;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Services\TelegramSettingsService;

class SettingsController extends Controller
{
    public function switchLanguage(Request $request)
    {
        $data = $request->validate([
            'language' => 'required|in:en,km',
        ]);

        $language = $data['language'];
        $user = $request->session()->get('user', []);
        $user['language'] = $language;

        $request->session()->put('user', $user);
        $request->session()->put('user.language', $language);

        if (auth()->check() && Schema::hasColumn('users', 'language')) {
            DB::table('users')
                ->where('id', auth()->id())
                ->update(['language' => $language]);
        }

        return back();
    }

    public function invoicePrefix()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $columns = ['id', 'name', 'location_id'];
        $optionalColumns = ['invoice_prefix', 'invoice_scheme_id', 'receipt_printer_type', 'mobile', 'alternate_number'];
        foreach ($optionalColumns as $col) {
            if (Schema::hasColumn('business_locations', $col)) {
                $columns[] = $col;
            }
        }
        $locations = BusinessLocation::where('business_id', $business_id)->orderBy('name')->get($columns);
        $hasInvoicePrefix = Schema::hasColumn('business_locations', 'invoice_prefix');

        return view('loanmanagement::settings.invoice_prefix', compact('locations', 'hasInvoicePrefix'));
    }

    public function updateInvoicePrefix(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $prefixes = (array) $request->input('invoice_prefixes', []);
        $hasInvoicePrefix = Schema::hasColumn('business_locations', 'invoice_prefix');

        if (! $hasInvoicePrefix) {
            return redirect()
                ->route('loan-management.settings')
                ->with('status', ['success' => 1, 'msg' => 'Your POS version does not support invoice_prefix column. No updates were applied.']);
        }

        foreach ($prefixes as $location_id => $prefix) {
            $clean = trim((string) $prefix);
            $clean = $clean !== '' ? mb_substr($clean, 0, 50) : null;

            BusinessLocation::where('business_id', $business_id)
                ->where('id', (int) $location_id)
                ->update(['invoice_prefix' => $clean]);
        }

        return redirect()
            ->route('loan-management.settings')
            ->with('status', ['success' => 1, 'msg' => 'Invoice prefix settings updated successfully.']);
    }

    public function paymentMethods()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $business = Business::find($businessId);
        $paymentTypes = app(TransactionUtil::class)->payment_types(null, true, $businessId);
        $customLabels = json_decode($business->custom_labels ?? '[]', true) ?: [];

        $locations = BusinessLocation::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'location_id', 'default_payment_accounts']);

        $usage = $this->loanPaymentMethodUsage();
        $legacyRows = $this->legacyPaymentMethodRows();

        return view('loanmanagement::settings.payment_methods', compact(
            'paymentTypes',
            'customLabels',
            'locations',
            'usage',
            'legacyRows'
        ));
    }

    public function updatePaymentMethods(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = $request->session()->get('user.business_id');
        $paymentTypes = app(TransactionUtil::class)->payment_types(null, true, $businessId);
        $customLabelsInput = (array) $request->input('custom_labels', []);
        $enabledByLocation = (array) $request->input('enabled', []);

        $business = Business::findOrFail($businessId);
        $customLabels = json_decode($business->custom_labels ?? '[]', true) ?: [];
        foreach (range(1, 7) as $number) {
            $key = 'custom_pay_'.$number;
            if (array_key_exists($key, $customLabelsInput)) {
                $label = trim((string) $customLabelsInput[$key]);
                if ($label !== '') {
                    $customLabels['payments'][$key] = mb_substr($label, 0, 191);
                } else {
                    unset($customLabels['payments'][$key]);
                }
            }
        }
        $business->custom_labels = json_encode($customLabels);
        $business->save();

        $locations = BusinessLocation::where('business_id', $businessId)->get(['id', 'default_payment_accounts']);
        foreach ($locations as $location) {
            $accounts = json_decode($location->default_payment_accounts ?? '[]', true) ?: [];
            foreach ($paymentTypes as $method => $label) {
                if ($method === 'advance') {
                    continue;
                }
                $accounts[$method] = $accounts[$method] ?? ['account' => null];
                $accounts[$method]['is_enabled'] = ! empty($enabledByLocation[$location->id][$method]) ? 1 : 0;
                $accounts[$method]['account'] = $accounts[$method]['account'] ?? null;
            }
            $location->default_payment_accounts = json_encode($accounts);
            $location->save();
        }

        return redirect()
            ->route('loan-management.settings.payment-methods')
            ->with('status', ['success' => 1, 'msg' => 'Payment method settings updated successfully.']);
    }

    public function telegram()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $settings = TelegramSettingsService::get();
        $webhookUrl = url('/webhook/loan-telegram');

        return view('loanmanagement::settings.telegram', compact('settings', 'webhookUrl'));
    }

    public function updateTelegram(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'bot_token' => 'nullable|string|max:255',
            'bot_username' => 'nullable|string|max:255|regex:/^[A-Za-z0-9_]*$/',
            'webhook_secret' => 'nullable|string|max:255',
            'link_ttl_minutes' => 'required|integer|min:1|max:1440',
        ]);

        TelegramSettingsService::save([
            'bot_token' => trim((string) ($data['bot_token'] ?? '')),
            'bot_username' => trim((string) ($data['bot_username'] ?? ''), '@'),
            'webhook_secret' => trim((string) ($data['webhook_secret'] ?? '')),
            'link_ttl_minutes' => (int) $data['link_ttl_minutes'],
        ]);

        return redirect()
            ->route('loan-management.settings.telegram')
            ->with('status', ['success' => 1, 'msg' => 'Telegram bot settings saved.']);
    }

    public function generateTelegramWebhookSecret()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        return response()->json(['secret' => bin2hex(random_bytes(24))]);
    }

    public function testTelegramConnection(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $token = trim((string) $request->input('bot_token')) ?: TelegramSettingsService::botToken();
        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'Enter a bot token first.'], 422);
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not reach Telegram: '.$e->getMessage()], 502);
        }

        if ($response->failed() || ! $response->json('ok')) {
            return response()->json(['success' => false, 'message' => 'Telegram rejected this token: '.$response->body()], 422);
        }

        $bot = (array) $response->json('result');

        return response()->json([
            'success' => true,
            'message' => 'Connected successfully.',
            'bot_name' => $bot['first_name'] ?? '',
            'bot_username' => $bot['username'] ?? '',
        ]);
    }

    public function registerTelegramWebhook(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $token = TelegramSettingsService::botToken();
        $secret = TelegramSettingsService::webhookSecret();

        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'Save a bot token before registering the webhook.'], 422);
        }
        if ($secret === '') {
            return response()->json(['success' => false, 'message' => 'Save a webhook secret before registering the webhook.'], 422);
        }

        $webhookUrl = url('/webhook/loan-telegram');

        try {
            $response = Http::timeout(15)->asForm()->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $secret,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not reach Telegram: '.$e->getMessage()], 502);
        }

        if ($response->failed() || ! $response->json('ok')) {
            return response()->json(['success' => false, 'message' => 'setWebhook failed: '.$response->body()], 422);
        }

        TelegramSettingsService::markWebhookRegistered($webhookUrl);

        return response()->json(['success' => true, 'message' => 'Webhook registered: '.$webhookUrl]);
    }

    protected function loanPaymentMethodUsage(): array
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            return [];
        }

        $columns = Schema::connection('mysql_loan')->getColumnListing('loan_payments');
        $methodColumn = in_array('payment_method_snapshot', $columns, true)
            ? 'payment_method_snapshot'
            : (in_array('channel', $columns, true) ? 'channel' : null);
        $amountColumn = in_array('total_paid_base', $columns, true)
            ? 'total_paid_base'
            : (in_array('amount', $columns, true) ? 'amount' : null);

        if (empty($methodColumn) || empty($amountColumn)) {
            return [];
        }

        $methodExpression = "COALESCE(NULLIF($methodColumn, ''), 'Unknown')";

        return DB::connection('mysql_loan')->table('loan_payments')
            ->selectRaw("$methodExpression as method_name, COUNT(*) as payments_count, SUM($amountColumn) as total_amount")
            ->groupBy(DB::raw($methodExpression))
            ->orderByDesc('total_amount')
            ->get()
            ->keyBy('method_name')
            ->map(fn ($row) => [
                'payments_count' => (int) $row->payments_count,
                'total_amount' => (float) $row->total_amount,
            ])
            ->all();
    }

    protected function legacyPaymentMethodRows()
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_payment_methods')) {
            return DB::connection('mysql_loan')->table('loan_payment_methods')->orderBy('name')->get();
        }

        if (Schema::hasTable('payment_methods')) {
            return DB::table('payment_methods')->orderBy('name')->get();
        }

        return collect();
    }
}
