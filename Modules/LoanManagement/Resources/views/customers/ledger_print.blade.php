<!DOCTYPE html>
<html lang="{{ session('user.language', config('app.locale')) }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Customer Ledger — {{ $customerRow->customer_code ?? $customerRow->id }}</title>
<link rel="stylesheet" href="{{ asset('css/loan-customer-detail.css') }}?v=20261029"><link rel="stylesheet" href="{{ asset('css/loan-customer-ledger.css') }}?v=20261029">
</head><body class="lcl-print-page"><p class="lcl-print-business">{{ \Modules\LoanManagement\Services\BusinessSettingsService::get()['business_name'] }}</p>@include('loanmanagement::customers.partials.ledger')<script>window.addEventListener('load', function () { window.print(); });</script></body></html>
