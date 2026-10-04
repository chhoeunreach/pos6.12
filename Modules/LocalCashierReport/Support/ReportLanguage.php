<?php

namespace Modules\LocalCashierReport\Support;

class ReportLanguage
{
    public static function current(): string
    {
        $requested = request('report_lang');
        if (in_array($requested, ['en', 'km'], true)) {
            return $requested;
        }

        return 'km';
    }

    public static function text(string $key, ?string $language = null): string
    {
        return trans('localcashierreport::report.' . $key, [], $language ?? self::current());
    }

    public static function label(string $label, ?string $language = null): string
    {
        $aliases = [
            'លក់' => 'sale',
            'Collection Payment' => 'collection_payment', 'Collection payment' => 'collection_payment',
            'Customer Payment' => 'customer_payment', 'Customer payment' => 'customer_payment',
        ];
        if (isset($aliases[$label])) {
            return self::text($aliases[$label], $language);
        }

        $english = trans('localcashierreport::report', [], 'en');
        $khmer = trans('localcashierreport::report', [], 'km');
        $key = array_search($label, $english, true);
        if ($key === false) {
            $key = array_search($label, $khmer, true);
        }

        return $key === false ? $label : self::text($key, $language);
    }

    public static function payment(string $method, string $label, ?string $language = null): string
    {
        $keys = [
            'cash' => 'cash', 'card' => 'card', 'cheque' => 'cheque',
            'bank_transfer' => 'bank_transfer', 'other' => 'other',
        ];

        if (isset($keys[$method])) {
            return self::text($keys[$method], $language);
        }
        $knownLabels = [
            'wing' => 'wing', 'aba' => 'aba', 'acleda' => 'acleda',
            'true money' => 'true', 'truemoney' => 'true', 'true' => 'true',
            'e-money' => 'emoney', 'emoney' => 'emoney', 'cut' => 'cut',
            'monthly' => 'monthly', 'កាត់អីវ៉ាន់' => 'cut',
        ];
        $normalized = strtolower(trim($label));
        if (isset($knownLabels[$normalized])) {
            return self::text($knownLabels[$normalized], $language);
        }
        if (preg_match('/^([^()]+)\s*\(([^()]+)\)$/u', $label, $matches)) {
            return ($language ?? self::current()) === 'km' ? trim($matches[1]) : trim($matches[2]);
        }

        return self::label($label, $language);
    }
}
