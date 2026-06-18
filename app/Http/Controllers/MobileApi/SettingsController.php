<?php

namespace App\Http\Controllers\MobileApi;

use App\Business;
use App\BusinessLocation;
use App\PaymentAccount;
use App\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * @group Settings
 * Business settings and configuration
 */
class SettingsController extends BaseController
{
    public function index()
    {
        $business_id = $this->getBusinessId();

        $business = Business::with('currency')->findOrFail($business_id);

        $permitted_locations = $this->getPermittedLocations();
        $location_query = BusinessLocation::where('business_id', $business_id)->active();
        if ($permitted_locations != 'all') {
            $location_query->whereIn('id', $permitted_locations);
        }
        $locations = $location_query->get(['id', 'name', 'location_id', 'landmark', 'city', 'state', 'country']);

        $tax_rates = TaxRate::where('business_id', $business_id)->get(['id', 'name', 'amount', 'is_tax_group']);

        $payment_accounts = Schema::hasTable('payment_accounts')
            ? PaymentAccount::where('business_id', $business_id)->get(['id', 'name', 'account_type'])
            : collect();

        $settings = [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'start_date' => $business->start_date,
                'default_profit_percent' => $business->default_profit_percent,
                'currency' => $business->currency,
                'currency_precision' => $business->currency_precision,
                'quantity_precision' => $business->quantity_precision,
                'time_format' => $business->time_format,
            ],
            'locations' => $locations,
            'tax_rates' => $tax_rates,
            'payment_accounts' => $payment_accounts,
        ];

        return $this->success($settings);
    }

    public function paymentMethods()
    {
        $payment_types = [
            ['key' => 'cash', 'label' => 'Cash'],
            ['key' => 'card', 'label' => 'Card'],
            ['key' => 'cheque', 'label' => 'Cheque'],
            ['key' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['key' => 'advance', 'label' => 'Advance'],
            ['key' => 'custom_pay_1', 'label' => 'Custom 1'],
            ['key' => 'custom_pay_2', 'label' => 'Custom 2'],
            ['key' => 'custom_pay_3', 'label' => 'Custom 3'],
            ['key' => 'custom_pay_4', 'label' => 'Custom 4'],
            ['key' => 'custom_pay_5', 'label' => 'Custom 5'],
            ['key' => 'custom_pay_6', 'label' => 'Custom 6'],
            ['key' => 'custom_pay_7', 'label' => 'Custom 7'],
        ];

        return $this->success($payment_types);
    }

    public function business()
    {
        $business_id = $this->getBusinessId();

        $business = Business::with('currency', 'locations')->findOrFail($business_id);

        return $this->success([
            'id' => $business->id,
            'name' => $business->name,
            'business_address' => $business->business_address,
            'currency' => $business->currency,
            'locations' => $business->locations->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->location_address,
                    'mobile' => $loc->mobile,
                    'email' => $loc->email,
                ];
            }),
            'tax_number_1' => $business->tax_number_1,
            'tax_label_1' => $business->tax_label_1,
            'tax_number_2' => $business->tax_number_2,
            'tax_label_2' => $business->tax_label_2,
            'logo' => $business->logo,
        ]);
    }
}
