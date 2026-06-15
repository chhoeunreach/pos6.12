@php
    $isAllSaleTable = $isAllSaleTable ?? false;
    $saleRowsForFilters = collect($rows ?? []);
    $allSaleLocations = $saleRowsForFilters->pluck('location_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    $allSaleCashiers = $saleRowsForFilters->pluck('cashier_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
@endphp

@if($isAllSaleTable)
    <div class="row all-sale-table-filters">
        <div class="col-md-3 col-sm-6">
            <div class="form-group">
                <label>Location</label>
                <select class="form-control all-sale-location-filter" data-table-id="{{ $tableId }}">
                    <option value="">All locations</option>
                    @foreach($allSaleLocations as $locationName)
                        <option value="{{ $locationName }}">{{ $locationName }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="form-group">
                <label>Cashier</label>
                <select class="form-control all-sale-cashier-filter" data-table-id="{{ $tableId }}">
                    <option value="">All cashiers</option>
                    @foreach($allSaleCashiers as $cashierName)
                        <option value="{{ $cashierName }}">{{ $cashierName }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
@endif

<div class="table-responsive">
    <table class="table table-bordered table-striped ajax_view local-module-sales-detail-table" id="{{ $tableId }}" style="width:100%;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice No</th>
                <th>I-T</th>
                @unless($isAllSaleTable)
                    <th>Location</th>
                @endunless
                <th>Customer</th>
                @unless($isAllSaleTable)
                    <th>Group</th>
                @endunless
                <th>SKU</th>
                <th>Product Name</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                @unless($isAllSaleTable)
                    <th class="text-right">Line Total</th>
                    <th class="text-right">Discount</th>
                @endunless
                <th class="text-right">Total Paid</th>
                @foreach($report['payment_columns'] as $method)
                    <th class="text-right">{{ $report['payment_labels'][$method] ?? $method }}</th>
                @endforeach
                <th class="text-right">Due</th>
                @if($isAllSaleTable)
                    <th class="never-visible all-sale-location-column">Location</th>
                    <th class="never-visible all-sale-cashier-column">Cashier</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment-customer-row' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon-customer-row' : 'normal-customer-row') }}">
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['invoice_no'] }}</td>
                    <td>{{ $row['i_t'] ?? '-' }}</td>
                    @unless($isAllSaleTable)
                        <td>{{ $row['location_name'] }}</td>
                    @endunless
                    <td>{{ $row['customer_name'] ?? '-' }}</td>
                    @unless($isAllSaleTable)
                        <td>
                            <span class="customer-group-pill {{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon' : 'normal') }}">
                                {{ $row['customer_group_name'] ?? 'លក់' }}
                            </span>
                        </td>
                    @endunless
                    <td>{{ $row['sku'] }}</td>
                    <td>{{ $row['product_name'] }}</td>
                    <td class="text-right">{{ is_null($row['quantity'] ?? null) ? '' : rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                    <td class="text-right">{{ is_null($row['unit_price'] ?? null) ? '' : $fmt($row['unit_price']) }}</td>
                    @unless($isAllSaleTable)
                        <td class="text-right">{{ is_null($row['line_total'] ?? null) ? '' : $fmt($row['line_total']) }}</td>
                        <td class="text-right">{{ is_null($row['discount'] ?? null) ? '' : $fmt($row['discount']) }}</td>
                    @endunless
                    <td class="text-right">{{ $fmt($row['paid']) }}</td>
                    @foreach($report['payment_columns'] as $method)
                        <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                    @endforeach
                    <td class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                    @if($isAllSaleTable)
                        <td>{{ $row['location_name'] ?? 'N/A' }}</td>
                        <td>{{ $row['cashier_name'] ?? 'N/A' }}</td>
                    @endif
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
                <th colspan="{{ $isAllSaleTable ? 6 : 8 }}" class="text-right">Total</th>
                <th class="text-right">{{ rtrim(rtrim(number_format($saleRows->sum(fn ($row) => (float) ($row['quantity'] ?? 0)), 2), '0'), '.') }}</th>
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['unit_price'] ?? 0))) }}</th>
                @unless($isAllSaleTable)
                    <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['line_total'] ?? 0))) }}</th>
                    <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['discount'] ?? 0))) }}</th>
                @endunless
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['paid'] ?? 0))) }}</th>
                @foreach($report['payment_columns'] as $method)
                    <th class="text-right">{{ $fmt($paymentTotals[$method] ?? 0) }}</th>
                @endforeach
                <th class="text-right @if($saleRows->sum(fn ($row) => (float) ($row['due'] ?? 0)) < 0) due-negative @endif">
                    {{ $fmt($saleRows->sum(fn ($row) => (float) ($row['due'] ?? 0))) }}
                </th>
                @if($isAllSaleTable)
                    <th></th>
                    <th></th>
                @endif
            </tr>
        </tfoot>
    </table>
</div>
