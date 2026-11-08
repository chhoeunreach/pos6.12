@php
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key);
    $symbol = (string) data_get(session('currency'), 'symbol', '$');
    $priceColumns = \App\Services\LotHistoryPrices::COLUMNS;
@endphp
<!doctype html>
<html lang="{{ \Modules\LocalCashierReport\Support\ReportLanguage::current() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('lang_v1.lot_history') }} · {{ $lot }}</title>
<link rel="stylesheet" href="{{ asset('css/lot-history.css') }}?v={{ filemtime(public_path('css/lot-history.css')) }}"></head>
<body class="lot-history-page"><header><h1>{{ __('lang_v1.lot_history') }} <span>{{ $lot }}</span></h1><p>{{ $product->name }} · {{ $product->sku }} · {{ $t('finance_source_'.$sourceName) }}</p><small>{{ $t('finance_lot_history_scope') }}</small></header>
<div class="history-table"><table><thead><tr>
    <th>{{ __('messages.date') }}</th><th>{{ $t('finance_lot_type') }}</th><th>{{ $t('finance_lot_reference') }}</th><th>{{ $t('location') }}</th>
    <th class="number">{{ $t('finance_lot_in') }}</th><th class="number">{{ $t('finance_lot_out') }}</th>
    @foreach($priceColumns as $column => $types)<th class="number">{{ $t('finance_lot_'.$column) }}</th>@endforeach
</tr></thead>
<tbody>@foreach($movements as $movement)<tr>
    <td>{{ $movement->transaction_date }}</td><td>{{ $t('finance_lot_type_'.$movement->type) }}</td><td>{{ $movement->reference ?: '#'.$movement->id }}</td><td>{{ $names[$movement->location_id] ?? '—' }}</td>
    <td class="number in">{{ $movement->qty_in > 0 ? number_format($movement->qty_in, 2) : '—' }}</td><td class="number out">{{ $movement->qty_out > 0 ? number_format($movement->qty_out, 2) : '—' }}</td>
    @foreach($priceColumns as $types)<td class="number">{{ in_array($movement->type, $types, true) && $movement->unit_price !== null ? $symbol.number_format($movement->unit_price, 2) : '—' }}</td>@endforeach
</tr>@endforeach</tbody></table></div>
</body></html>
