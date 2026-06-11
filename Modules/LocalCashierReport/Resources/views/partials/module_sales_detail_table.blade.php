<div class="table-responsive">
    <table class="table table-bordered table-striped ajax_view local-module-sales-detail-table" id="{{ $tableId }}" style="width:100%;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice No</th>
                <th>I-T</th>
                <th>Location</th>
                <th>Customer</th>
                <th>Group</th>
                <th>SKU</th>
                <th>Product Name</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Line Total</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Total Paid</th>
                @foreach($report['payment_columns'] as $method)
                    <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                @endforeach
                <th class="text-right">Due</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment-customer-row' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon-customer-row' : 'normal-customer-row') }}">
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['invoice_no'] }}</td>
                    <td>{{ $row['i_t'] ?? '-' }}</td>
                    <td>{{ $row['location_name'] }}</td>
                    <td>{{ $row['customer_name'] ?? '-' }}</td>
                    <td>
                        <span class="customer-group-pill {{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon' : 'normal') }}">
                            {{ $row['customer_group_name'] ?? 'លក់' }}
                        </span>
                    </td>
                    <td>{{ $row['sku'] }}</td>
                    <td>{{ $row['product_name'] }}</td>
                    <td class="text-right">{{ is_null($row['quantity'] ?? null) ? '' : rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                    <td class="text-right">{{ is_null($row['unit_price'] ?? null) ? '' : $fmt($row['unit_price']) }}</td>
                    <td class="text-right">{{ is_null($row['line_total'] ?? null) ? '' : $fmt($row['line_total']) }}</td>
                    <td class="text-right">{{ is_null($row['discount'] ?? null) ? '' : $fmt($row['discount']) }}</td>
                    <td class="text-right">{{ $fmt($row['paid']) }}</td>
                    @foreach($report['payment_columns'] as $method)
                        <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                    @endforeach
                    <td class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                </tr>
            @endforeach
        </tbody>
        @php
            $saleRows = collect($rows ?? []);
            $paymentTotals = [];
            foreach ($report['payment_columns'] as $method) {
                $paymentTotals[$method] = $saleRows->sum(fn ($row) => (float) data_get($row, 'payments.' . $method, 0));
            }
        @endphp
        <tfoot>
            <tr class="detail-total-row">
                <th colspan="8" class="text-right">Total</th>
                <th class="text-right">{{ rtrim(rtrim(number_format($saleRows->sum(fn ($row) => (float) ($row['quantity'] ?? 0)), 2), '0'), '.') }}</th>
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['unit_price'] ?? 0))) }}</th>
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['line_total'] ?? 0))) }}</th>
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['discount'] ?? 0))) }}</th>
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['paid'] ?? 0))) }}</th>
                @foreach($report['payment_columns'] as $method)
                    <th class="text-right">{{ $fmt($paymentTotals[$method] ?? 0) }}</th>
                @endforeach
                <th class="text-right @if($saleRows->sum(fn ($row) => (float) ($row['due'] ?? 0)) < 0) due-negative @endif">
                    {{ $fmt($saleRows->sum(fn ($row) => (float) ($row['due'] ?? 0))) }}
                </th>
            </tr>
        </tfoot>
    </table>
</div>
