<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;

class BusinessSettingsService
{
    protected const DEFAULTS = [
        'business_name' => 'KY Store',
        'legal_name' => 'NTL CO., LTD',
        'tax_number' => '',
        'company_phone' => '+855 23 888 999',
        'company_email' => 'contact@ntl-loan.com',
        'company_address' => 'Phnom Penh, Cambodia',
        'license_number' => 'NBC-MFI-2024-001',
        'system_name' => 'Loan Management',
        'system_subtitle' => 'Dedicated loan operation workspace',
        'start_date' => null,
        'default_interest_rate' => '1.50',
        'interest_rate_period' => 'monthly',
        'default_interest_method' => 'flat',
        'grace_period_days' => 3,
        'penalty_type' => 'percentage',
        'penalty_value' => '0.10',
        'min_loan_amount' => '100.00',
        'max_loan_amount' => '50000.00',
        'loan_prefix' => 'LN-',
        'customer_prefix' => 'CUST-',
        'receipt_prefix' => 'REC-',
        'quotation_prefix' => 'QUO-',
        'default_profit_percent' => '25.00',
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'currency_symbol_placement' => 'before',
        'time_zone' => 'Asia/Phnom_Penh',
        'fy_start_month' => 1,
        'stock_accounting_method' => 'fifo',
        'transaction_edit_days' => 365,
        'date_format' => 'd-m-Y',
        'time_format' => 24,
        'currency_precision' => 2,
        'quantity_precision' => 2,
        'theme_color' => '#6366f1',
        'logo_path' => null,
        'stamp_path' => null,
        'receipt_printer_type' => 'thermal_80mm',
        'contract_terms' => "១. អ្នកខ្ចីត្រូវបង់ប្រាក់សងតាមកាលវិភាគដែលបានកំណត់។\n២. ក្នុងករណីយឺតយ៉ាវហួសកាលកំណត់ ការផាកពិន័យនឹងត្រូវអនុវត្តតាមច្បាប់ជាធរមាន។\n៣. ភាគីទាំងពីរបានយល់ព្រមលើលក្ខខណ្ឌទាំងអស់ដែលមានចែងក្នុងកិច្ចសន្យានេះ។",
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',
        'notify_new_loan' => true,
        'notify_payment_received' => true,
        'notify_overdue_daily' => true,
        'login_background_path' => null,
        'cms_enabled' => true,
        'home_cms' => [],
        'home_hero_path' => null,
        'customer_login_enabled' => true,
        'demo_customer_login_enabled' => true,
        'demo_admin_login_enabled' => false,
        'home_headline' => 'Simple loan service for customers',
        'home_subtitle' => 'Register with NTL CO.LTD and our team will contact you about your loan request.',
        'home_body' => 'Fast customer registration, clear payment schedules, Telegram updates, and easy support from our branch team.',
        'invoice_message_template' => "❤️ **{Customer Name}** អរគុណសម្រាប់ការទូទាត់ និងការទុកចិត្តលើ **{Business Name}**។\n🧾 វិក្កយបត្ររបស់អ្នកសូមមើលខាងក្រោម។",
    ];

    public static function get(): array
    {
        $settings = self::read();

        $result = array_merge(self::DEFAULTS, array_intersect_key($settings, self::DEFAULTS));
        $result['home_cms'] = CmsHomeService::normalize($result['home_cms'] ?? []);
        return $result;
    }

    public static function save(array $data): void
    {
        $current = self::get();
        $payload = [
            'business_name' => self::cleanText($data['business_name'] ?? $current['business_name'], 80),
            'legal_name' => self::cleanText($data['legal_name'] ?? $current['legal_name'], 120),
            'tax_number' => self::cleanText($data['tax_number'] ?? $current['tax_number'], 50),
            'company_phone' => self::cleanText($data['company_phone'] ?? $current['company_phone'], 50),
            'company_email' => self::cleanText($data['company_email'] ?? $current['company_email'], 100),
            'company_address' => self::cleanText($data['company_address'] ?? $current['company_address'], 250),
            'license_number' => self::cleanText($data['license_number'] ?? $current['license_number'], 50),
            'system_name' => self::cleanText($data['system_name'] ?? $current['system_name'], 80),
            'system_subtitle' => self::cleanText($data['system_subtitle'] ?? $current['system_subtitle'], 120),
            'start_date' => self::cleanDate($data['start_date'] ?? $current['start_date']),
            'default_interest_rate' => self::cleanDecimal($data['default_interest_rate'] ?? ($data['default_profit_percent'] ?? $current['default_interest_rate']), 0, 1000, 2),
            'interest_rate_period' => self::cleanChoice($data['interest_rate_period'] ?? $current['interest_rate_period'], ['monthly', 'yearly'], self::DEFAULTS['interest_rate_period']),
            'default_interest_method' => self::cleanChoice($data['default_interest_method'] ?? $current['default_interest_method'], ['flat', 'declining', 'annuity'], self::DEFAULTS['default_interest_method']),
            'grace_period_days' => self::cleanInteger($data['grace_period_days'] ?? $current['grace_period_days'], 0, 365, self::DEFAULTS['grace_period_days']),
            'penalty_type' => self::cleanChoice($data['penalty_type'] ?? $current['penalty_type'], ['percentage', 'fixed'], self::DEFAULTS['penalty_type']),
            'penalty_value' => self::cleanDecimal($data['penalty_value'] ?? $current['penalty_value'], 0, 100000, 2),
            'min_loan_amount' => self::cleanDecimal($data['min_loan_amount'] ?? $current['min_loan_amount'], 0, 10000000, 2),
            'max_loan_amount' => self::cleanDecimal($data['max_loan_amount'] ?? $current['max_loan_amount'], 0, 10000000, 2),
            'loan_prefix' => self::cleanText($data['loan_prefix'] ?? $current['loan_prefix'], 20),
            'customer_prefix' => self::cleanText($data['customer_prefix'] ?? $current['customer_prefix'], 20),
            'receipt_prefix' => self::cleanText($data['receipt_prefix'] ?? $current['receipt_prefix'], 20),
            'quotation_prefix' => self::cleanText($data['quotation_prefix'] ?? $current['quotation_prefix'], 20),
            'default_profit_percent' => self::cleanDecimal($data['default_profit_percent'] ?? ($data['default_interest_rate'] ?? $current['default_profit_percent']), 0, 1000, 2),
            'currency_code' => self::cleanText($data['currency_code'] ?? $current['currency_code'], 10) ?: self::DEFAULTS['currency_code'],
            'currency_symbol' => self::cleanText($data['currency_symbol'] ?? $current['currency_symbol'], 10) ?: self::DEFAULTS['currency_symbol'],
            'currency_symbol_placement' => self::cleanChoice($data['currency_symbol_placement'] ?? $current['currency_symbol_placement'], ['before', 'after'], self::DEFAULTS['currency_symbol_placement']),
            'time_zone' => self::cleanText($data['time_zone'] ?? $current['time_zone'], 80) ?: self::DEFAULTS['time_zone'],
            'fy_start_month' => self::cleanInteger($data['fy_start_month'] ?? $current['fy_start_month'], 1, 12, self::DEFAULTS['fy_start_month']),
            'stock_accounting_method' => self::cleanChoice($data['stock_accounting_method'] ?? $current['stock_accounting_method'], ['fifo', 'lifo', 'avco'], self::DEFAULTS['stock_accounting_method']),
            'transaction_edit_days' => self::cleanInteger($data['transaction_edit_days'] ?? $current['transaction_edit_days'], 0, 3650, self::DEFAULTS['transaction_edit_days']),
            'date_format' => self::cleanChoice($data['date_format'] ?? $current['date_format'], ['d-m-Y', 'm-d-Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'], self::DEFAULTS['date_format']),
            'time_format' => (int) self::cleanChoice($data['time_format'] ?? $current['time_format'], ['12', '24'], (string) self::DEFAULTS['time_format']),
            'currency_precision' => self::cleanInteger($data['currency_precision'] ?? $current['currency_precision'], 0, 4, self::DEFAULTS['currency_precision']),
            'quantity_precision' => self::cleanInteger($data['quantity_precision'] ?? $current['quantity_precision'], 0, 4, self::DEFAULTS['quantity_precision']),
            'theme_color' => self::cleanColor($data['theme_color'] ?? $current['theme_color']),
            'logo_path' => $data['logo_path'] ?? $current['logo_path'],
            'stamp_path' => array_key_exists('stamp_path', $data) ? $data['stamp_path'] : $current['stamp_path'],
            'receipt_printer_type' => self::cleanChoice($data['receipt_printer_type'] ?? $current['receipt_printer_type'], ['thermal_80mm', 'a4', 'a5'], self::DEFAULTS['receipt_printer_type']),
            'contract_terms' => self::cleanMultilineText($data['contract_terms'] ?? $current['contract_terms'], 5000, self::DEFAULTS['contract_terms']),
            'telegram_bot_token' => self::cleanText($data['telegram_bot_token'] ?? $current['telegram_bot_token'], 120),
            'telegram_chat_id' => self::cleanText($data['telegram_chat_id'] ?? $current['telegram_chat_id'], 120),
            'notify_new_loan' => filter_var($data['notify_new_loan'] ?? $current['notify_new_loan'], FILTER_VALIDATE_BOOLEAN),
            'notify_payment_received' => filter_var($data['notify_payment_received'] ?? $current['notify_payment_received'], FILTER_VALIDATE_BOOLEAN),
            'notify_overdue_daily' => filter_var($data['notify_overdue_daily'] ?? $current['notify_overdue_daily'], FILTER_VALIDATE_BOOLEAN),
            'login_background_path' => $data['login_background_path'] ?? $current['login_background_path'],
            'cms_enabled' => filter_var($data['cms_enabled'] ?? $current['cms_enabled'], FILTER_VALIDATE_BOOLEAN),
            'customer_login_enabled' => filter_var($data['customer_login_enabled'] ?? $current['customer_login_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'demo_customer_login_enabled' => filter_var($data['demo_customer_login_enabled'] ?? $current['demo_customer_login_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'demo_admin_login_enabled' => filter_var($data['demo_admin_login_enabled'] ?? $current['demo_admin_login_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'home_headline' => self::cleanText($data['home_headline'] ?? $current['home_headline'], 140),
            'home_subtitle' => self::cleanText($data['home_subtitle'] ?? $current['home_subtitle'], 220),
            'home_body' => self::cleanMultilineText($data['home_body'] ?? $current['home_body'], 1200, ''),
            'home_cms' => CmsHomeService::normalize($data['home_cms'] ?? $current['home_cms']),
            'home_hero_path' => array_key_exists('home_hero_path', $data) ? $data['home_hero_path'] : $current['home_hero_path'],
            'invoice_message_template' => self::cleanMultilineText($data['invoice_message_template'] ?? $current['invoice_message_template'], 2000, self::DEFAULTS['invoice_message_template']),
        ];

        $path = self::path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function isCustomerLoginEnabled(): bool
    {
        return (bool) (self::get()['customer_login_enabled'] ?? true);
    }

    public static function seedCmsDefaults(): void
    {
        $stored = self::read();
        $existingCms = is_array($stored['home_cms'] ?? null) ? $stored['home_cms'] : [];
        $defaults = CmsHomeService::sampleDefaults();
        $cms = array_merge($defaults, $existingCms);
        $legacyDefaults = [
            'brands_title' => 'Explore our brands',
            'guide_description' => 'Choose a product, submit your request, and track your account.',
            'step_2_body' => 'Register once and send your selected items to our team.',
            'step_3_title' => 'Track your account',
            'step_3_body' => 'Log in to view your loan records and payment history.',
        ];
        foreach ($legacyDefaults as $key => $value) {
            if (($existingCms[$key] ?? null) === $value) {
                $cms[$key] = $defaults[$key];
            }
        }
        if (empty($existingCms['brands_items']) && ($existingCms['brands_source'] ?? 'catalog') === 'catalog') {
            $cms['brands_items'] = $defaults['brands_items'];
        }
        self::save(array_merge([
            'home_headline' => 'Simple loan service for customers',
            'home_subtitle' => 'Choose your products and send an installment request. Our staff will contact you.',
            'home_body' => 'Installment shopping, clear payment schedules, and personal support from our showroom team.',
        ], $stored, ['home_cms' => $cms]));
    }

    public static function isDemoCustomerLoginEnabled(): bool
    {
        return (bool) (self::get()['demo_customer_login_enabled'] ?? true);
    }

    public static function isDemoAdminLoginEnabled(): bool
    {
        return (bool) (self::get()['demo_admin_login_enabled'] ?? false);
    }

    public static function businessName(): string
    {
        return self::get()['business_name'];
    }

    public static function systemName(): string
    {
        return self::get()['system_name'];
    }

    public static function systemSubtitle(): string
    {
        return self::get()['system_subtitle'];
    }

    public static function themeColor(): string
    {
        return self::get()['theme_color'];
    }

    public static function isCmsEnabled(): bool
    {
        return (bool) self::get()['cms_enabled'];
    }

    public static function invoiceMessageTemplate(): string
    {
        return self::get()['invoice_message_template'];
    }

    public static function sessionPayload(): array
    {
        $settings = self::get();

        return [
            'business.name' => $settings['business_name'],
            'business.start_date' => $settings['start_date'],
            'business.default_profit_percent' => $settings['default_profit_percent'],
            'business.currency_symbol_placement' => $settings['currency_symbol_placement'],
            'business.time_zone' => $settings['time_zone'],
            'business.fy_start_month' => $settings['fy_start_month'],
            'business.stock_accounting_method' => $settings['stock_accounting_method'],
            'business.transaction_edit_days' => $settings['transaction_edit_days'],
            'business.date_format' => $settings['date_format'],
            'business.time_format' => $settings['time_format'],
            'business.currency_precision' => $settings['currency_precision'],
            'business.quantity_precision' => $settings['quantity_precision'],
            'currency.code' => $settings['currency_code'],
            'currency.symbol' => $settings['currency_symbol'],
        ];
    }

    public static function invoiceMessage(string $customerName = ''): string
    {
        $settings = self::get();
        $replacements = [
            '{Customer Name}' => trim($customerName) !== '' ? trim($customerName) : 'Customer',
            '{Business Name}' => $settings['business_name'] ?: 'KY Store',
        ];

        return strtr($settings['invoice_message_template'], $replacements);
    }

    public static function logoUrl(): ?string
    {
        $path = self::get()['logo_path'] ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        if (Route::has('loan-management.settings.business.logo')) {
            return route('loan-management.settings.business.logo');
        }

        return Storage::disk('public')->url($path);
    }

    public static function publicLogoUrl(): ?string
    {
        $path = self::get()['logo_path'] ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        if (Route::has('loan-management.settings.business.public-logo')) {
            return route('loan-management.settings.business.public-logo');
        }

        return Storage::disk('public')->url($path);
    }

    public static function loginBackgroundUrl(): ?string
    {
        $path = self::get()['login_background_path'] ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        if (Route::has('loan-management.settings.business.login-background')) {
            return route('loan-management.settings.business.login-background');
        }

        return Storage::disk('public')->url($path);
    }

    public static function deleteLogo(?string $path = null): void
    {
        $path = $path ?: (self::get()['logo_path'] ?? null);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function deleteLoginBackground(?string $path = null): void
    {
        $path = $path ?: (self::get()['login_background_path'] ?? null);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function stampUrl(): ?string
    {
        $path = self::get()['stamp_path'] ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        if (Route::has('loan-management.settings.business.stamp')) {
            return route('loan-management.settings.business.stamp');
        }

        return Storage::disk('public')->url($path);
    }

    public static function deleteStamp(?string $path = null): void
    {
        $path = $path ?: (self::get()['stamp_path'] ?? null);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function cssVariables(): string
    {
        $color = self::themeColor();
        [$r, $g, $b] = self::hexToRgb($color);
        $dark = self::rgbToHex((int) round($r * 0.82), (int) round($g * 0.82), (int) round($b * 0.82));
        $light = self::mixWithWhite($r, $g, $b, 0.25);
        $tone50 = self::mixWithWhite($r, $g, $b, 0.92);
        $tone100 = self::mixWithWhite($r, $g, $b, 0.84);
        $tone200 = self::mixWithWhite($r, $g, $b, 0.70);

        return implode("\n", [
            "--lm-primary: {$color};",
            "--lm-primary-dark: {$dark};",
            "--lm-primary-light: {$light};",
            "--lm-primary-50: {$tone50};",
            "--lm-primary-100: {$tone100};",
            "--lm-primary-200: {$tone200};",
            "--lm-sidebar-active: {$color};",
            "--lm-primary-rgb: {$r}, {$g}, {$b};",
        ]);
    }

    public static function hasSavedSettings(): bool
    {
        return is_file(self::path());
    }

    protected static function read(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function path(): string
    {
        return storage_path('app/loan-management/business_settings.json');
    }

    protected static function cleanText($value, int $length): string
    {
        $clean = trim((string) $value);

        return $clean !== '' ? mb_substr($clean, 0, $length) : '';
    }

    protected static function cleanMultilineText($value, int $length, string $default = ''): string
    {
        $clean = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));

        return $clean !== '' ? mb_substr($clean, 0, $length) : $default;
    }

    protected static function cleanColor($value): string
    {
        $color = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : self::DEFAULTS['theme_color'];
    }

    protected static function cleanChoice($value, array $allowed, string $default): string
    {
        $clean = trim((string) $value);

        return in_array($clean, $allowed, true) ? $clean : $default;
    }

    protected static function cleanDate($value): ?string
    {
        $clean = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean) ? $clean : null;
    }

    protected static function cleanDecimal($value, float $min, float $max, int $precision): string
    {
        $number = max($min, min($max, (float) $value));

        return number_format($number, $precision, '.', '');
    }

    protected static function cleanInteger($value, int $min, int $max, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected static function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    protected static function mixWithWhite(int $r, int $g, int $b, float $whiteRatio): string
    {
        return self::rgbToHex(
            (int) round(($r * (1 - $whiteRatio)) + (255 * $whiteRatio)),
            (int) round(($g * (1 - $whiteRatio)) + (255 * $whiteRatio)),
            (int) round(($b * (1 - $whiteRatio)) + (255 * $whiteRatio))
        );
    }
}
