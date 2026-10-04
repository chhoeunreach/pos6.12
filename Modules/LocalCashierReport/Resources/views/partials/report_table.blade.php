@php
    $currentReportLang = \Modules\LocalCashierReport\Support\ReportLanguage::current();
    $t = fn ($key) => \Modules\LocalCashierReport\Support\ReportLanguage::text($key, $currentReportLang);
@endphp
<div class="box box-solid" style="font-family: {{ $khmerFontFamily ?? "'KhmerFont', 'Noto Sans Khmer', sans-serif" }};">
    <div class="box-body table-responsive">
        @if(!empty($isPdf))
            <h3 style="margin:0 0 10px 0;">{{ $t('local_cashier_report') }}</h3>
            <table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse: collapse; margin-bottom: 10px;">
                <tr style="background:#ffe65a;">
                    <th>{{ $t('total_sale') }}</th><th>{{ $t('total_paid') }}</th><th>{{ $t('total_due') }}</th><th>{{ $t('total_discount') }}</th><th>{{ $t('total_qty_sold') }}</th>
                </tr>
                <tr>
                    <td>{{ $currencySymbol }}{{ number_format(data_get($summary, 'cards.total_sale', 0), 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format(data_get($summary, 'cards.total_paid', 0), 2) }}</td>
                    <td style="color:red;">{{ $currencySymbol }}{{ number_format(data_get($summary, 'cards.total_due', 0), 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format(data_get($summary, 'cards.total_discount', 0), 2) }}</td>
                    <td>{{ number_format(data_get($summary, 'cards.total_qty', 0), 2) }}</td>
                </tr>
            </table>
        @endif

        <table class="table table-bordered table-striped ajax_view" id="local_cashier_report_table" width="100%">
            <thead>
                <tr>
                    <th>{{ $t('action') }}</th><th>{{ $t('date') }}</th><th>{{ $t('invoice_no') }}</th><th>{{ $t('cashier_user') }}</th><th>{{ $t('location') }}</th><th>{{ $t('sku') }}</th><th>{{ $t('product_name') }}</th><th>{{ $t('quantity') }}</th><th>{{ $t('unit_price') }}</th><th>{{ $t('line_total') }}</th><th>{{ $t('discount') }}</th><th>{{ $t('total_paid') }}</th><th>{{ \Modules\LocalCashierReport\Support\ReportLanguage::payment('cash', (string) ($paymentLabels['cash'] ?? 'Cash')) }}</th><th>{{ \Modules\LocalCashierReport\Support\ReportLanguage::payment('aba', (string) ($paymentLabels['aba'] ?? 'ABA')) }}</th><th>{{ \Modules\LocalCashierReport\Support\ReportLanguage::payment('acleda', (string) ($paymentLabels['acleda'] ?? 'ACLEDA')) }}</th><th>{{ \Modules\LocalCashierReport\Support\ReportLanguage::payment('wing', (string) ($paymentLabels['wing'] ?? 'WING')) }}</th><th>{{ $paymentLabels['e_and_t'] ?? 'E&T' }}</th><th>{{ \Modules\LocalCashierReport\Support\ReportLanguage::payment('card', (string) ($paymentLabels['card'] ?? 'Card')) }}</th><th>{{ \Modules\LocalCashierReport\Support\ReportLanguage::payment('other', (string) ($paymentLabels['other'] ?? 'Other')) }}</th><th>{{ $t('due') }}</th>
                </tr>
            </thead>
            @if(!empty($isPdf))
            <tbody>
                @foreach($rows as $i => $row)
                <tr style="background:#e9f5ff;">
                    <td>-</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('Y-m-d H:i') }}</td>
                    <td>{{ $row->invoice_no }}</td>
                    <td>{{ $row->cashier_name }}</td>
                    <td>{{ $row->location_name }}</td>
                    <td>{{ $row->sku }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td>{{ number_format($row->quantity, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->unit_price, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->line_total, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->discount, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->total_paid, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->cash, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->aba, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->acleda, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->wing, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->e_and_t, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->card, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($row->other, 2) }}</td>
                    <td style="color:red;">{{ $currencySymbol }}{{ number_format($row->due, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            @endif
        </table>
    </div>
</div>
