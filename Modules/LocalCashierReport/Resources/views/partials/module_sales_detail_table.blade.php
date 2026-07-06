@php
    $isAllSaleTable = $isAllSaleTable ?? false;
    $saleRowsForFilters = collect($rows ?? []);
    $allSaleLocations = $saleRowsForFilters->pluck('location_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    $allSaleCashiers = $saleRowsForFilters->pluck('cashier_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    $staticPaymentColumns = $staticPaymentColumns ?? (isset($report) ? (array) data_get($report, 'static_payment_columns', []) : []);
    $staticPaymentAmount = function ($row, $column) {
        $payments = (array) data_get($row, 'payments', []);
        $sources = (array) data_get($column, 'source_methods', []);
        $amount = 0.0;
        foreach ($sources as $source) {
            $amount += (float) ($payments[$source] ?? 0);
        }
        return $amount;
    };
    $shortPaymentLabel = function ($method, $label) {
        $method = (string) $method;
        $label = trim((string) $label);
        $map = [
            'cash' => 'Cash',
            'card' => 'Card',
            'other' => 'Other',
        ];

        if (isset($map[$method])) {
            return $map[$method];
        }
        if (stripos($label, 'wing') !== false || str_contains($label, 'វីង')) {
            return 'Wing';
        }
        if (stripos($label, 'aba') !== false || str_contains($label, 'អេប៊ីអេ')) {
            return 'ABA';
        }
        if (stripos($label, 'acleda') !== false || str_contains($label, 'អេស៊ីលីដា')) {
            return 'Acleda';
        }
        if (stripos($label, 'true money') !== false || stripos($label, 'truemoney') !== false || str_contains($label, 'ទ្រូម៉ានី')) {
            return 'True';
        }
        if (str_contains($label, 'កាត់អីវ៉ាន់')) {
            return 'Cut';
        }

        return $label !== '' ? $label : $method;
    };
@endphp

<div class="sale-table-filter-toggle">
    <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#{{ $tableId }}_filters" aria-expanded="false" aria-controls="{{ $tableId }}_filters">
        <i class="fa fa-filter"></i> Filters
    </button>
</div>
<div class="collapse" id="{{ $tableId }}_filters">
    <div class="row all-sale-table-filters">
            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label>Location</label>
                    <select class="form-control select2 all-sale-location-filter" data-table-id="{{ $tableId }}" multiple data-placeholder="All locations">
                        @foreach($allSaleLocations as $locationName)
                            <option value="{{ $locationName }}">{{ $locationName }}</option>
                        @endforeach
                    </select>
                    <div class="all-sale-filter-actions">
                        <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-location-filter" data-table-id="{{ $tableId }}">Select All</button>
                        <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-location-filter" data-table-id="{{ $tableId }}">Clear</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label>Cashier</label>
                    <select class="form-control select2 all-sale-cashier-filter" data-table-id="{{ $tableId }}" multiple data-placeholder="All cashiers">
                        @foreach($allSaleCashiers as $cashierName)
                            <option value="{{ $cashierName }}">{{ $cashierName }}</option>
                        @endforeach
                    </select>
                    <div class="all-sale-filter-actions">
                        <button type="button" class="btn btn-xs btn-default all-sale-select-all" data-target=".all-sale-cashier-filter" data-table-id="{{ $tableId }}">Select All</button>
                        <button type="button" class="btn btn-xs btn-default all-sale-clear-select" data-target=".all-sale-cashier-filter" data-table-id="{{ $tableId }}">Clear</button>
                    </div>
                </div>
            </div>
    </div>
</div>

<div class="table-responsive {{ $isAllSaleTable ? 'all-sale-fit-table-wrapper' : '' }}">
    <table class="table table-bordered table-striped ajax_view local-module-sales-detail-table" id="{{ $tableId }}" style="width:100%;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice No</th>
                <th>I-T</th>
                @unless($isAllSaleTable)
                    <th class="sale-location-column all-sale-location-column">Location</th>
                @endunless
                <th>{{ $isAllSaleTable ? 'Cust.' : 'Customer' }}</th>
                @if($isAllSaleTable)
                    <th>Phone</th>
                @endif
                @unless($isAllSaleTable)
                    <th>Group</th>
                    <th>Lot</th>
                @endunless
                <th>SKU</th>
                <th>{{ $isAllSaleTable ? 'Product' : 'Product Name' }}</th>
                <th class="text-right">{{ $isAllSaleTable ? 'Qty' : 'Quantity' }}</th>
                <th class="text-right">{{ $isAllSaleTable ? 'Price' : 'Unit Price' }}</th>
                @if($isAllSaleTable)
                    <th class="text-right">Total</th>
                @else
                    <th class="text-right">Line Total</th>
                    <th class="text-right">Discount</th>
                @endif
                @if($isAllSaleTable)
                    @foreach($staticPaymentColumns as $column)
                        <th class="text-right">{{ $column['label'] ?? '' }}</th>
                    @endforeach
                @else
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $isAllSaleTable ? $shortPaymentLabel($method, $report['payment_labels'][$method] ?? $method) : ($report['payment_labels'][$method] ?? $method) }}</th>
                    @endforeach
                @endif
                <th class="text-right">{{ $isAllSaleTable ? 'Paid' : 'Total Paid' }}</th>
                <th class="text-right">Due</th>
                @if($isAllSaleTable)
                    <th class="all-sale-location-column">Location</th>
                    <th>Group</th>
                    <th class="all-sale-cashier-column">User/Cashier</th>
                @else
                    <th class="all-sale-cashier-column">User/Cashier</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php
                    $transactionId = (int) ($row['transaction_id'] ?? 0);
                    $rowSource = (string) ($row['row_source'] ?? 'sell');
                    $modulePrefix = (string) ($row['module_prefix'] ?? '');
                    if ($modulePrefix === '' && in_array($rowSource, ['accessory_sale', 'service_sale'])) {
                        $modulePrefix = str_replace('_sale', '', $rowSource);
                    }
                    $viewUrl = null;
                    $editUrl = null;
                    if ($transactionId > 0 && $rowSource !== 'loan_payment') {
                        if (in_array($modulePrefix, ['accessory', 'service'])) {
                            $viewUrl = url($modulePrefix . '/sells/' . $transactionId);
                            $editUrl = url($modulePrefix . '/sells/' . $transactionId . '/edit');
                        } else {
                            $viewUrl = action([\App\Http\Controllers\SellController::class, 'show'], [$transactionId]);
                            $editUrl = action([\App\Http\Controllers\SellPosController::class, 'edit'], [$transactionId]);
                        }
                    }
                    $fullDate = (string) ($row['date'] ?? '');
                    $displayDate = $isAllSaleTable && preg_match('/^\d{4}-\d{2}-\d{2}/', $fullDate)
                        ? substr($fullDate, 0, 10)
                        : $fullDate;
                @endphp
                <tr class="{{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment-customer-row' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon-customer-row' : 'normal-customer-row') }}">
                    <td>
                        @if($isAllSaleTable && ($viewUrl || $editUrl))
                            <span class="date-action-wrap">
                                <span class="date-action-text" title="{{ $fullDate }}">{{ $displayDate }}</span>
                                <span class="date-action-popover">
                                    @if($viewUrl)
                                        <a class="date-action-pill btn-modal"
                                           href="#"
                                           data-href="{{ $viewUrl }}"
                                           data-container=".view_modal"
                                           title="View">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    @endif
                                    @if($editUrl)
                                        <a class="date-action-pill date-action-edit" href="{{ $editUrl }}" target="_blank" title="Edit">
                                            <i class="fas fa-pen"></i> Edit
                                        </a>
                                    @endif
                                </span>
                            </span>
                        @else
                            <span title="{{ $fullDate }}">{{ $displayDate }}</span>
                        @endif
                    </td>
                    <td>{{ $row['invoice_no'] }}</td>
                    <td>{{ $row['i_t'] ?? '-' }}</td>
                    @unless($isAllSaleTable)
                        <td>{{ $row['location_name'] }}</td>
                    @endunless
                    @if($isAllSaleTable)
                        <td title="Location: {{ $row['location_name'] ?? 'N/A' }}&#10;Cashier: {{ $row['cashier_name'] ?? 'N/A' }}">{{ $row['customer_name'] ?? '-' }}</td>
                        <td>{{ $row['phone_number'] ?? '' }}</td>
                    @else
                        <td>{{ $row['customer_name'] ?? '-' }}</td>
                    @endif
                    @unless($isAllSaleTable)
                        <td>
                            <span class="customer-group-pill {{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon' : 'normal') }}">
                                {{ $row['customer_group_name'] ?? 'លក់' }}
                            </span>
                        </td>
                        <td>{{ $row['lot_number'] ?? '-' }}</td>
                    @endunless
                    <td>{{ $row['sku'] }}</td>
                    <td>{{ $row['product_name'] }}</td>
                    <td class="text-right">{{ is_null($row['quantity'] ?? null) ? '' : rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                    <td class="text-right">{{ is_null($row['unit_price'] ?? null) ? '' : $fmt($row['unit_price']) }}</td>
                    @if($isAllSaleTable)
                        <td class="text-right">{{ is_null($row['line_total'] ?? null) ? '' : $fmt($row['line_total']) }}</td>
                    @else
                        <td class="text-right">{{ is_null($row['line_total'] ?? null) ? '' : $fmt($row['line_total']) }}</td>
                        <td class="text-right">{{ is_null($row['discount'] ?? null) ? '' : $fmt($row['discount']) }}</td>
                    @endif
                    @if($isAllSaleTable)
                        @foreach($staticPaymentColumns as $column)
                            <td class="text-right">{{ $fmt($staticPaymentAmount($row, $column)) }}</td>
                        @endforeach
                    @else
                        @foreach($report['payment_columns'] as $method)
                            <td class="text-right">{{ $fmt($row['payments'][$method] ?? null) }}</td>
                        @endforeach
                    @endif
                    <td class="text-right">{{ $fmt($row['paid']) }}</td>
                    <td class="text-right @if(($row['due'] ?? 0) < 0) due-negative @endif">{{ $fmt($row['due']) }}</td>
                    @if($isAllSaleTable)
                        <td>{{ $row['location_name'] ?? 'N/A' }}</td>
                        <td>
                            <span class="customer-group-pill {{ ($row['customer_group_name'] ?? '') === 'រំលស់' ? 'installment' : (($row['customer_group_name'] ?? '') === 'អ៊ីអន' ? 'aeon' : (($row['customer_group_name'] ?? '') === 'បង់ប្រាក់' ? 'loan-payment' : 'normal')) }}">
                                {{ $row['customer_group_name'] ?? 'លក់' }}
                            </span>
                        </td>
                        <td>{{ $row['cashier_name'] ?? 'N/A' }}</td>
                    @else
                        <td>{{ $row['cashier_name'] ?? 'N/A' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        @php
            $saleRows = collect($rows ?? []);
            if ($isAllSaleTable) {
                $paymentTotals = [];
                foreach ($staticPaymentColumns as $column) {
                    $paymentTotals[$column['key'] ?? ''] = $saleRows->sum(fn ($row) => $staticPaymentAmount($row, $column));
                }
            } else {
                $paymentTotals = [];
                foreach ($report['payment_columns'] as $method) {
                    $paymentTotals[$method] = $saleRows->sum(fn ($row) => (float) data_get($row, 'payments.' . $method, 0));
                }
            }
        @endphp
        <tfoot>
            <tr class="detail-total-row">
                <th colspan="{{ $isAllSaleTable ? 7 : 9 }}" class="text-right">Total</th>
                <th class="text-right">{{ rtrim(rtrim(number_format($saleRows->sum(fn ($row) => (float) ($row['quantity'] ?? 0)), 2), '0'), '.') }}</th>
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['unit_price'] ?? 0))) }}</th>
                @if($isAllSaleTable)
                    <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['line_total'] ?? 0))) }}</th>
                @else
                    <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['line_total'] ?? 0))) }}</th>
                    <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['discount'] ?? 0))) }}</th>
                @endif
                @if($isAllSaleTable)
                    @foreach($staticPaymentColumns as $column)
                        <th class="text-right">{{ $fmt($paymentTotals[$column['key'] ?? ''] ?? 0) }}</th>
                    @endforeach
                @else
                    @foreach($report['payment_columns'] as $method)
                        <th class="text-right">{{ $fmt($paymentTotals[$method] ?? 0) }}</th>
                    @endforeach
                @endif
                <th class="text-right">{{ $fmt($saleRows->sum(fn ($row) => (float) ($row['paid'] ?? 0))) }}</th>
                <th class="text-right @if($saleRows->sum(fn ($row) => (float) ($row['due'] ?? 0)) < 0) due-negative @endif">
                    {{ $fmt($saleRows->sum(fn ($row) => (float) ($row['due'] ?? 0))) }}
                </th>
                @if($isAllSaleTable)
                    <th></th>
                    <th></th>
                    <th></th>
                @else
                    <th></th>
                @endif
            </tr>
        </tfoot>
    </table>
</div>
