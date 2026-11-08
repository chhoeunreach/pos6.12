@extends('layouts.app')
@php $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key); @endphp
@section('title', $t('finance_quick_row_price'))
@section('content')
<section class="content-header"><h1>{{ $t('finance_quick_row_price') }} · {{ $receipt->ref_no ?: '#'.$receipt->id }}</h1></section>
<section class="content"><div class="box box-primary"><div class="box-body">
    <form method="GET" class="form-inline" style="margin-bottom:15px">
        <input type="hidden" name="report_lang" value="{{ \Modules\LocalCashierReport\Support\ReportLanguage::current() }}">
        <label for="purchase-row-search">{{ $t('finance_find_purchase_row') }}</label>
        <input id="purchase-row-search" class="form-control" name="q" value="{{ $search }}" maxlength="200">
        <button class="btn btn-primary" type="submit">{{ $t('apply') }}</button>
    </form>
    <div class="table-responsive"><table class="table table-bordered table-striped">
        <thead><tr><th>#</th><th>{{ $t('finance_cost_product') }}</th><th>{{ $t('finance_cost_sku') }}</th><th>{{ $t('finance_cost_lots') }}</th><th>{{ $t('finance_cost_qty') }}</th><th>{{ $t('finance_purchase_price') }}</th><th>{{ $t('edit') }}</th></tr></thead>
        <tbody>@forelse($rows as $row)<tr data-purchase-row="{{ $row->id }}">
            <td>{{ $row->id }}</td><td>{{ $row->product }}</td><td><a class="purchase-price-history-link" aria-haspopup="dialog" data-sku="{{ $row->sku }}" href="{{ route('reports.financial-purchase-price-history', ['sourceName' => $sourceName, 'variation' => $row->variation_id, 'report_lang' => \Modules\LocalCashierReport\Support\ReportLanguage::current()]) }}">{{ $row->sku }}</a></td><td>{{ $row->lot_number }}</td><td>{{ (float) $row->quantity }}</td><td class="purchase-row-price">{{ $row->purchase_price_inc_tax }}</td>
            <td><a class="btn btn-primary btn-sm purchase-row-price-edit" aria-haspopup="dialog" href="{{ route('reports.financial-purchase-line.show', ['sourceName' => $sourceName, 'line' => $row->id, 'report_lang' => \Modules\LocalCashierReport\Support\ReportLanguage::current()]) }}">{{ $t('edit') }}</a></td>
        </tr>@empty<tr><td colspan="7">{{ $t('no_data') }}</td></tr>@endforelse</tbody>
    </table></div>
    {{ $rows->links() }}
</div></div></section>
@include('purchase.partials.lot_price_modal', ['costEditorId' => 'purchase-row-price-modal', 'costEditorLabels' => ['title' => $t('finance_edit_purchase_price'), 'shared' => $t('finance_quick_row_hint'), 'cancel' => $t('cancel'), 'save' => $t('finance_cost_save'), 'loading' => $t('loading')]])
@include('purchase.partials.price_history_modal')
@endsection
@section('javascript')
<script src="{{ asset('js/purchase-price-history.js') }}?v={{ filemtime(public_path('js/purchase-price-history.js')) }}"></script>
<script src="{{ asset('js/purchase-lot-price-modal.js') }}?v={{ filemtime(public_path('js/purchase-lot-price-modal.js')) }}"></script>
<script src="{{ asset('js/purchase-row-list.js') }}?v={{ filemtime(public_path('js/purchase-row-list.js')) }}"></script>
@endsection
