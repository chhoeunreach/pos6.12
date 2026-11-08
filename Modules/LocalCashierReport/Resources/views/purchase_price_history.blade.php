@php
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key);
    $symbol = (string) data_get(session('currency'), 'symbol', '$');
    $columns = ['date' => $t('date'), 'reference' => $t('finance_lot_reference'), 'location' => $t('location'),
        'lot' => $t('finance_cost_lots'), 'quantity' => $t('finance_cost_qty'), 'purchase_price' => $t('finance_purchase_price').' ('.__('product.inc_of_tax').')'];
@endphp
<!doctype html>
<html lang="{{ \Modules\LocalCashierReport\Support\ReportLanguage::current() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $t('finance_purchase_price_history') }} · {{ $product->sku }}</title>
<link rel="stylesheet" href="{{ asset('css/lot-history.css') }}?v={{ filemtime(public_path('css/lot-history.css')) }}">
<style>.purchase-history-sort{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px}.purchase-history-sort:hover{text-decoration:underline}.purchase-history-sort:focus-visible{outline:2px solid #24568a;outline-offset:3px}</style></head>
<body class="lot-history-page"><header><h1>{{ $t('finance_purchase_price_history') }} <span>{{ $product->sku }}</span></h1><p>{{ $product->name }} · {{ $t('finance_source_'.$sourceName) }}</p><small>{{ $t('finance_purchase_price_history_scope') }}</small></header>
<div class="history-table"><table><thead><tr>
@foreach($columns as $key => $label)
    <th class="{{ in_array($key, ['quantity', 'purchase_price']) ? 'number' : '' }}" aria-sort="{{ $sort === $key ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
        <a class="purchase-history-sort" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}">{{ $label }} <span aria-hidden="true">{{ $sort === $key ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}</span></a>
    </th>
@endforeach
</tr></thead>
<tbody>@forelse($rows as $row)<tr><td>{{ $row->transaction_date }}</td><td>{{ $row->ref_no ?: '#'.$row->purchase_id }}</td><td>{{ $row->location ?: '—' }}</td><td>{{ $row->lot_number ?: '—' }}</td><td class="number">{{ (float) $row->quantity }}</td><td class="number">{{ $row->purchase_price_inc_tax === null ? '—' : $symbol.number_format($row->purchase_price_inc_tax, 4) }}</td></tr>
@empty<tr><td colspan="6">{{ $t('no_data') }}</td></tr>@endforelse</tbody></table></div>
<p>{{ $rows->total() }} · {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
@if($rows->previousPageUrl()) · <a href="{{ $rows->previousPageUrl() }}">{{ $t('previous') }}</a>@endif
@if($rows->nextPageUrl()) · <a href="{{ $rows->nextPageUrl() }}">{{ $t('next') }}</a>@endif</p>
</body></html>
