@extends('layouts.app')
@php $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key); @endphp
@section('title', $t('finance_quick_row_price'))
@section('content')
<section class="content-header"><h1>{{ $t('finance_quick_row_price') }}</h1></section>
<section class="content">
    <div class="box box-primary" style="max-width:760px">
        <div class="box-header with-border"><h3 class="box-title">{{ $row->ref_no ?: '#'.$row->transaction_id }} · #{{ $row->id }}</h3></div>
        <div class="box-body">
            <p><strong>{{ $row->product }}</strong> · {{ $row->sku }} · {{ $row->lot_number }}</p>
            <p>{{ $t('finance_cost_qty') }}: {{ (float) $row->quantity }}</p>
            <p class="text-muted">{{ $t('finance_quick_row_hint') }}</p>
            <form id="purchase-line-price-form" action="{{ $details['update_url'] }}" method="POST"
                data-saving="{{ $t('loading') }}" data-saved="{{ $t('finance_quick_row_saved') }}" data-error="{{ $t('finance_quick_row_error') }}">
                @csrf
                <input type="hidden" name="cost_version" value="{{ $details['cost_version'] }}">
                <input type="hidden" name="prices[0][id]" value="{{ $row->id }}">
                <div id="purchase-line-price-status" role="status" hidden></div>
                <div class="form-group">
                    <label for="purchase-line-price">{{ $t('finance_purchase_price') }} ({{ __('product.inc_of_tax') }})</label>
                    <input class="form-control" type="number" id="purchase-line-price" name="prices[0][purchase_price]" min="0" max="999999999999" step="0.0001" required autofocus value="{{ $details['lots'][0]['purchase_price'] }}">
                </div>
                <button type="submit" class="btn btn-primary">{{ $t('finance_cost_save') }}</button>
                <a class="btn btn-default" href="{{ route('reports.financial-purchase-line.index', ['sourceName' => $sourceName, 'purchase' => $row->transaction_id, 'report_lang' => \Modules\LocalCashierReport\Support\ReportLanguage::current()]) }}">{{ $t('finance_find_purchase_row') }}</a>
                @php $fullRoute = ($sourceName === 'main' ? '' : $sourceName.'.').'purchases.edit'; @endphp
                @if(\Illuminate\Support\Facades\Route::has($fullRoute))
                    <a class="btn btn-default" href="{{ route($fullRoute, $row->transaction_id) }}" target="_blank" rel="noopener">{{ $t('finance_open_full_purchase') }} ↗</a>
                @endif
            </form>
        </div>
    </div>
</section>
@endsection
@section('javascript')
<script src="{{ asset('js/purchase-line-price.js') }}?v={{ filemtime(public_path('js/purchase-line-price.js')) }}"></script>
@endsection
