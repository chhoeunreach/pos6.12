@php
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key);
    $symbol = (string) data_get(session('currency'), 'symbol', '$');
@endphp
<!doctype html>
<html lang="{{ \Modules\LocalCashierReport\Support\ReportLanguage::current() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('lang_v1.lot_history') }} · {{ $lot }}</title>
<style>
@font-face{font-family:HistoryKhmer;src:url('{{ asset('fonts/khmer/NotoSansKhmer-Regular.ttf') }}')}*{box-sizing:border-box}body{margin:0;padding:22px;background:#f5f7fb;color:#183b5b;font:13px/1.8 HistoryKhmer,Arial,sans-serif}header{margin-bottom:20px}h1{font-size:23px;margin:0 0 8px}p{margin:0;color:#657b91}.history-table{overflow:auto;background:#fff;border:1px solid #e1e8f0;border-radius:12px}table{border-collapse:collapse;width:100%;white-space:nowrap}th,td{padding:13px 16px;border-bottom:1px solid #edf1f5;text-align:left}th{background:#edf3fa;color:#49637d;font-size:12px}tbody tr:last-child td{border:0}.number{text-align:right;font-variant-numeric:tabular-nums}.in{color:#167449}.out{color:#be3546}small{font-size:11px;color:#718399}
</style></head>
<body><header><h1>{{ __('lang_v1.lot_history') }} · {{ $lot }}</h1><p>{{ $product->name }} · {{ $product->sku }} · {{ $t('finance_source_'.$sourceName) }}</p><small>{{ $t('finance_lot_history_scope') }}</small></header>
<div class="history-table"><table><thead><tr><th>{{ __('messages.date') }}</th><th>{{ $t('finance_lot_type') }}</th><th>{{ $t('finance_lot_reference') }}</th><th>{{ $t('location') }}</th><th class="number">{{ $t('finance_lot_in') }}</th><th class="number">{{ $t('finance_lot_out') }}</th><th class="number">{{ __('lang_v1.purchase_price_inc_tax') }}</th></tr></thead>
<tbody>@foreach($movements as $movement)<tr><td>{{ $movement->transaction_date }}</td><td>{{ $t('finance_lot_type_'.$movement->type) }}</td><td>{{ $movement->reference ?: '#'.$movement->id }}</td><td>{{ $names[$movement->location_id] ?? '—' }}</td><td class="number in">{{ $movement->qty_in > 0 ? number_format($movement->qty_in, 2) : '—' }}</td><td class="number out">{{ $movement->qty_out > 0 ? number_format($movement->qty_out, 2) : '—' }}</td><td class="number">{{ $movement->purchase_price === null ? '—' : $symbol.number_format($movement->purchase_price, 2) }}</td></tr>@endforeach</tbody></table></div>
</body></html>
