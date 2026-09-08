<?php

namespace Modules\LoanManagement\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Modules\LoanManagement\Services\TelegramSettingsService;
use Throwable;

class SettingsController extends Controller
{
    protected string $connection = 'mysql_loan';

    public function business()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $settings = BusinessSettingsService::get();
        $currencies = $this->businessCurrencyOptions();
        $timezones = DateTimeZone::listIdentifiers();

        return view('loanmanagement::settings.business', compact('settings', 'currencies', 'timezones'));
    }

    public function updateBusiness(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'business_name' => 'required|string|max:80',
            'system_name' => 'required|string|max:80',
            'system_subtitle' => 'nullable|string|max:120',
            'start_date' => 'nullable|date',
            'default_profit_percent' => 'required|numeric|min:0|max:1000',
            'currency_code' => 'required|string|max:10',
            'currency_symbol' => 'nullable|string|max:10',
            'currency_symbol_placement' => 'required|in:before,after',
            'time_zone' => 'required|string|max:80',
            'fy_start_month' => 'required|integer|min:1|max:12',
            'stock_accounting_method' => 'required|in:fifo,lifo,avco',
            'transaction_edit_days' => 'required|integer|min:0|max:3650',
            'date_format' => 'required|in:d-m-Y,m-d-Y,Y-m-d,d/m/Y,m/d/Y',
            'time_format' => 'required|in:12,24',
            'currency_precision' => 'required|integer|min:0|max:4',
            'quantity_precision' => 'required|integer|min:0|max:4',
            'theme_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_message_template' => 'required|string|max:2000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
            'login_background' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'cms_enabled' => 'nullable|boolean',
            'customer_login_enabled' => 'nullable|boolean',
            'demo_customer_login_enabled' => 'nullable|boolean',
            'demo_admin_login_enabled' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
            'remove_login_background' => 'nullable|boolean',
        ]);

        $current = BusinessSettingsService::get();
        $logoPath = $current['logo_path'] ?? null;
        $loginBackgroundPath = $current['login_background_path'] ?? null;

        if ($request->boolean('remove_logo')) {
            BusinessSettingsService::deleteLogo($logoPath);
            $logoPath = null;
        }

        if ($request->boolean('remove_login_background')) {
            BusinessSettingsService::deleteLoginBackground($loginBackgroundPath);
            $loginBackgroundPath = null;
        }

        if ($request->hasFile('logo')) {
            BusinessSettingsService::deleteLogo($logoPath);
            $logoPath = $request->file('logo')->store('loan-management/business', 'public');
        }

        if ($request->hasFile('login_background')) {
            BusinessSettingsService::deleteLoginBackground($loginBackgroundPath);
            $loginBackgroundPath = $request->file('login_background')->store('loan-management/business', 'public');
        }

        BusinessSettingsService::save([
            'business_name' => $data['business_name'],
            'system_name' => $data['system_name'],
            'system_subtitle' => $data['system_subtitle'] ?: 'Dedicated loan operation workspace',
            'start_date' => $data['start_date'] ?? null,
            'default_profit_percent' => $data['default_profit_percent'],
            'currency_code' => strtoupper($data['currency_code']),
            'currency_symbol' => $data['currency_symbol'] ?: $this->currencySymbolFor(strtoupper($data['currency_code'])),
            'currency_symbol_placement' => $data['currency_symbol_placement'],
            'time_zone' => $data['time_zone'],
            'fy_start_month' => $data['fy_start_month'],
            'stock_accounting_method' => $data['stock_accounting_method'],
            'transaction_edit_days' => $data['transaction_edit_days'],
            'date_format' => $data['date_format'],
            'time_format' => $data['time_format'],
            'currency_precision' => $data['currency_precision'],
            'quantity_precision' => $data['quantity_precision'],
            'theme_color' => strtolower($data['theme_color']),
            'cms_enabled' => $request->boolean('cms_enabled'),
            'customer_login_enabled' => $request->boolean('customer_login_enabled'),
            'demo_customer_login_enabled' => $request->boolean('demo_customer_login_enabled'),
            'demo_admin_login_enabled' => $request->boolean('demo_admin_login_enabled'),
            'home_headline' => $current['home_headline'],
            'home_subtitle' => $current['home_subtitle'],
            'home_body' => $current['home_body'],
            'invoice_message_template' => $data['invoice_message_template'],
            'logo_path' => $logoPath,
            'login_background_path' => $loginBackgroundPath,
        ]);

        $request->session()->put(BusinessSettingsService::sessionPayload());

        return redirect()
            ->route('loan-management.settings.business')
            ->with('status', ['success' => 1, 'msg' => 'Business settings updated successfully.']);
    }

    public function cms()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $settings = BusinessSettingsService::get();

        return view('loanmanagement::settings.cms', compact('settings'));
    }

    public function updateCms(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'home_headline' => 'required|string|max:140',
            'home_subtitle' => 'required|string|max:220',
            'home_body' => 'nullable|string|max:1200',
        ]);

        BusinessSettingsService::save(array_merge(BusinessSettingsService::get(), [
            'home_headline' => $data['home_headline'],
            'home_subtitle' => $data['home_subtitle'],
            'home_body' => $data['home_body'] ?? '',
        ]));

        return redirect()
            ->route('loan-management.settings.cms')
            ->with('status', ['success' => 1, 'msg' => 'CMS updated successfully.']);
    }

    public function businessLogo()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $settings = BusinessSettingsService::get();
        $path = $settings['logo_path'] ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    public function businessPublicLogo()
    {
        $settings = BusinessSettingsService::get();
        $path = $settings['logo_path'] ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    public function businessLoginBackground()
    {
        $settings = BusinessSettingsService::get();
        $path = $settings['login_background_path'] ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    public function switchLanguage(Request $request)
    {
        $langParam = $request->input('language') ?? $request->query('language') ?? $request->query('lang');
        $language = in_array($langParam, ['en', 'km'], true) ? $langParam : 'en';

        $user = $request->session()->get('user', []);
        $user['language'] = $language;

        $request->session()->put('user', $user);
        $request->session()->put('user.language', $language);

        if (auth()->check() && Schema::hasColumn('users', 'language')) {
            DB::table('users')
                ->where('id', auth()->id())
                ->update(['language' => $language]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'language' => $language])
                ->withCookie(cookie()->forever('lm_lang', $language));
        }

        return back()->withCookie(cookie()->forever('lm_lang', $language));
    }

    public function invoicePrefix()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureLoanLocationSettingsColumns();
        $locations = $this->loanLocations();
        $hasInvoicePrefix = Schema::connection($this->connection)->hasColumn('loan_business_locations', 'loan_invoice_prefix');

        return view('loanmanagement::settings.invoice_prefix', compact('locations', 'hasInvoicePrefix'));
    }

    public function updateInvoicePrefix(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $prefixes = (array) $request->input('invoice_prefixes', []);
        $this->ensureLoanLocationSettingsColumns();
        $hasInvoicePrefix = Schema::connection($this->connection)->hasColumn('loan_business_locations', 'loan_invoice_prefix');

        if (! $hasInvoicePrefix) {
            return redirect()
                ->route('loan-management.settings')
                ->with('status', ['success' => 1, 'msg' => 'Your POS version does not support invoice_prefix column. No updates were applied.']);
        }

        foreach ($prefixes as $location_id => $prefix) {
            $clean = trim((string) $prefix);
            $clean = $clean !== '' ? mb_substr($clean, 0, 50) : null;

            DB::connection($this->connection)
                ->table('loan_business_locations')
                ->where('id', (int) $location_id)
                ->update(['loan_invoice_prefix' => $clean, 'updated_at' => now()]);
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

        $this->ensurePaymentMethodSettingsColumns();
        $paymentTypes = $this->loanPaymentTypes();
        $paymentMethods = DB::connection($this->connection)
            ->table('loan_payment_methods')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $usage = $this->loanPaymentMethodUsage();
        $legacyRows = $this->legacyPaymentMethodRows();

        return view('loanmanagement::settings.payment_methods', compact(
            'paymentTypes',
            'paymentMethods',
            'usage',
            'legacyRows'
        ));
    }

    public function updatePaymentMethods(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensurePaymentMethodSettingsColumns();
        $rows = (array) $request->input('methods', []);

        foreach ($rows as $id => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            DB::connection($this->connection)
                ->table('loan_payment_methods')
                ->where('id', (int) $id)
                ->update($this->paymentMethodPayload([
                    'name' => mb_substr($name, 0, 191),
                    'code' => trim((string) ($row['code'] ?? '')),
                    'is_active' => ! empty($row['is_active']) ? 1 : 0,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'updated_at' => now(),
                ]));
        }

        $newName = trim((string) $request->input('new_method.name', ''));
        if ($newName !== '') {
            DB::connection($this->connection)
                ->table('loan_payment_methods')
                ->updateOrInsert(
                    ['name' => mb_substr($newName, 0, 191)],
                    $this->paymentMethodPayload([
                        'code' => trim((string) $request->input('new_method.code', '')),
                        'is_active' => 1,
                        'sort_order' => (int) $request->input('new_method.sort_order', 99),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );
        }

        return redirect()
            ->route('loan-management.settings.payment-methods')
            ->with('status', ['success' => 1, 'msg' => 'Payment method settings updated successfully.']);
    }

    public function currencies()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureCurrencySettingsColumns();
        $currencies = DB::connection($this->connection)
            ->table('loan_currencies')
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get();

        return view('loanmanagement::settings.currencies', compact('currencies'));
    }

    public function updateCurrencies(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $this->ensureCurrencySettingsColumns();
        $rows = (array) $request->input('currencies', []);
        $defaultCode = trim((string) $request->input('default_currency', ''));

        foreach ($rows as $id => $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }

            DB::connection($this->connection)
                ->table('loan_currencies')
                ->where('id', (int) $id)
                ->update($this->currencyPayload([
                    'code' => mb_substr($code, 0, 10),
                    'name' => mb_substr(trim((string) ($row['name'] ?? $code)), 0, 60),
                    'exchange_rate' => max(0.000001, (float) ($row['exchange_rate'] ?? 1)),
                    'is_default' => $defaultCode === $code ? 1 : 0,
                    'is_active' => ! empty($row['is_active']) ? 1 : 0,
                    'updated_at' => now(),
                ]));
        }

        $newCode = strtoupper(trim((string) $request->input('new_currency.code', '')));
        if ($newCode !== '') {
            DB::connection($this->connection)
                ->table('loan_currencies')
                ->updateOrInsert(
                    ['code' => mb_substr($newCode, 0, 10)],
                    $this->currencyPayload([
                        'name' => mb_substr(trim((string) $request->input('new_currency.name', $newCode)), 0, 60),
                        'exchange_rate' => max(0.000001, (float) $request->input('new_currency.exchange_rate', 1)),
                        'is_default' => $defaultCode === $newCode ? 1 : 0,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );
        }

        if ($defaultCode !== '') {
            DB::connection($this->connection)
                ->table('loan_currencies')
                ->where('code', '!=', $defaultCode)
                ->update(['is_default' => 0]);
        }

        return redirect()
            ->route('loan-management.settings.currencies')
            ->with('status', ['success' => 1, 'msg' => 'Currency settings updated successfully.']);
    }

    public function telegram()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $settings = TelegramSettingsService::get();
        $webhookUrl = trim((string) config('loanmanagement.telegram.webhook_url')) ?: url('/webhook/loan-telegram');

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

        $webhookUrl = trim((string) config('loanmanagement.telegram.webhook_url'));
        if ($webhookUrl === '') {
            $webhookUrl = url('/webhook/loan-telegram');
        }

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

    protected function loanLocations()
    {
        if (! Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            return collect();
        }

        return DB::connection($this->connection)
            ->table('loan_business_locations')
            ->when(Schema::connection($this->connection)->hasColumn('loan_business_locations', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('name')
            ->get();
    }

    protected function loanPaymentTypes(): array
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_methods')) {
            return [];
        }

        return DB::connection($this->connection)
            ->table('loan_payment_methods')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->code ?? null) ?: strtolower(str_replace(' ', '_', (string) $row->name)) => (string) $row->name])
            ->all();
    }

    protected function ensureLoanLocationSettingsColumns(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn('loan_business_locations', 'loan_invoice_prefix')) {
            Schema::connection($this->connection)->table('loan_business_locations', function ($table) {
                $table->string('loan_invoice_prefix', 50)->nullable();
            });
        }
    }

    protected function ensurePaymentMethodSettingsColumns(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_methods')) {
            Schema::connection($this->connection)->create('loan_payment_methods', function ($table) {
                $table->bigIncrements('id');
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        foreach ([
            'code' => fn ($table) => $table->string('code', 60)->nullable()->after('id')->index(),
            'sort_order' => fn ($table) => $table->integer('sort_order')->default(0)->after('is_active'),
        ] as $column => $creator) {
            if (! Schema::connection($this->connection)->hasColumn('loan_payment_methods', $column)) {
                Schema::connection($this->connection)->table('loan_payment_methods', fn ($table) => $creator($table));
            }
        }
    }

    protected function ensureCurrencySettingsColumns(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_currencies')) {
            Schema::connection($this->connection)->create('loan_currencies', function ($table) {
                $table->bigIncrements('id');
                $table->string('code', 10)->unique();
                $table->string('name', 60);
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function paymentMethodPayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip(Schema::connection($this->connection)->getColumnListing('loan_payment_methods')));
    }

    protected function currencyPayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip(Schema::connection($this->connection)->getColumnListing('loan_currencies')));
    }

    protected function businessCurrencyOptions()
    {
        try {
            $this->ensureCurrencySettingsColumns();

            $currencies = DB::connection($this->connection)
                ->table('loan_currencies')
                ->where(function ($query) {
                    $query->where('is_active', 1)->orWhereNull('is_active');
                })
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->get(['code', 'name']);

            if ($currencies->isNotEmpty()) {
                return $currencies;
            }
        } catch (Throwable $exception) {
            // Fall back to common currencies if the loan connection is unavailable.
        }

        return collect([
            (object) ['code' => 'USD', 'name' => 'United States of America - Dollars'],
            (object) ['code' => 'KHR', 'name' => 'Cambodian Riel'],
            (object) ['code' => 'THB', 'name' => 'Thai Baht'],
        ]);
    }

    protected function currencySymbolFor(string $code): string
    {
        return [
            'USD' => '$',
            'KHR' => '៛',
            'THB' => '฿',
        ][$code] ?? $code;
    }
}
