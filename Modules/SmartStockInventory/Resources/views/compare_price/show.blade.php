@extends('smartstockinventory::layouts.master')
@section('page_title', 'Compare Price — ' . $compare->ref_no)
@section('module_content')
@php
    $canExport = auth()->user()->can('stock_inventory.export') || auth()->user()->can('superadmin');
    $canUpdate = auth()->user()->can('stock_inventory.update') || auth()->user()->can('superadmin');
@endphp
@if($canExport)
    <input type="hidden" id="view_export_buttons">
@endif

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Compare Price: {{ $compare->ref_no }}
        @if($compare->title)<small>{{ $compare->title }}</small>@endif
    </h1>
</section>

<section class="content no-print">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Real Purchase Price Comparison'])
        @slot('tool')
            <div class="box-tools pull-right">
                <a href="{{ ssi_route('ssi.compare_price.index') }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left" aria-hidden="true"></i> All Lists
                </a>
                @if($canUpdate)
                    <a href="{{ ssi_route('ssi.compare_price.edit', ['id' => $compare->id]) }}" class="btn btn-sm btn-primary">
                        <i class="fa fa-edit" aria-hidden="true"></i> Edit / Re-Import
                    </a>
                @endif
                @if($canExport)
                    <a href="{{ ssi_route('ssi.compare_price.export', ['id' => $compare->id]) }}" class="btn btn-sm btn-success">
                        <i class="fa fa-file-excel-o" aria-hidden="true"></i> Export Excel (.xlsx)
                    </a>
                @endif
            </div>
        @endslot

        <div class="row" style="margin-bottom:12px;">
            <div class="col-md-3"><strong>Date:</strong> {{ $compare->compare_date }}</div>
            <div class="col-md-3"><strong>Location Scope:</strong> {{ $locationName }}</div>
            <div class="col-md-3"><strong>Total SKUs:</strong> {{ $enrichedLines->count() }}</div>
            <div class="col-md-3"><strong>Total Qty:</strong> <span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $enrichedLines->sum('qty') }}</span></div>
            @if($compare->notes)
                <div class="col-md-12" style="margin-top:6px;"><strong>Notes:</strong> {{ $compare->notes }}</div>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="compare_price_show_table" style="width:100%;">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Variant</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">តម្លៃដែលទិញចូលទាបជាងគេ</th>
                        <th class="text-right">តម្លៃមធ្យម</th>
                        <th class="text-right">តម្លៃខ្ពស់ជាងគេ</th>
                        <th class="text-right">តម្លៃទិញចូលក្រោយគេ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($enrichedLines as $row)
                        <tr>
                            <td>
                                <a href="{{ ssi_route('ssi.sku.history', ['variation' => $row['variation_id']]) }}"
                                   data-export-value="{{ $row['sku'] }}"
                                   title="Open SKU Transaction History">
                                    <strong>{{ $row['sku'] }}</strong>
                                </a>
                            </td>
                            <td>
                                <span data-export-value="{{ $row['product_name'] }}">{{ $row['product_name'] }}</span>
                                @if(($row['purchase_count'] ?? 0) > 0)
                                    <br><small class="text-muted">{{ $row['purchase_count'] }} purchase(s) · Latest: {{ $row['latest_ref_no'] }} ({{ \Illuminate\Support\Str::limit((string) $row['latest_date'], 10, '') }})</small>
                                @else
                                    <br><small class="text-muted">No received purchase</small>
                                @endif
                            </td>
                            <td>{{ $row['variant'] }}</td>
                            <td class="text-right">
                                <span class="display_currency" data-is_quantity="true" data-currency_symbol="false" data-orig-value="{{ $row['qty'] }}">{{ $row['qty'] }}</span>
                            </td>
                            <td class="text-right">
                                @if($canViewCosts && $row['min_price'] !== null)
                                    <span class="display_currency" data-currency_symbol="true" data-orig-value="{{ $row['min_price'] }}">{{ $row['min_price'] }}</span>
                                @else
                                    <span class="text-muted" data-export-value="">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($canViewCosts && $row['avg_price'] !== null)
                                    <span class="display_currency" data-currency_symbol="true" data-orig-value="{{ $row['avg_price'] }}">{{ $row['avg_price'] }}</span>
                                @else
                                    <span class="text-muted" data-export-value="">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($canViewCosts && $row['max_price'] !== null)
                                    <span class="display_currency" data-currency_symbol="true" data-orig-value="{{ $row['max_price'] }}">{{ $row['max_price'] }}</span>
                                @else
                                    <span class="text-muted" data-export-value="">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($canViewCosts && $row['latest_price'] !== null)
                                    <strong><span class="display_currency" data-currency_symbol="true" data-orig-value="{{ $row['latest_price'] }}">{{ $row['latest_price'] }}</span></strong>
                                @else
                                    <span class="text-muted" data-export-value="">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray font-17 footer-total">
                        <td colspan="3" class="text-right"><strong>Total Qty:</strong></td>
                        <td class="text-right">
                            <strong><span class="display_currency" data-is_quantity="true" data-currency_symbol="false">{{ $enrichedLines->sum('qty') }}</span></strong>
                        </td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endcomponent
</section>
@endsection

@section('module_js')
<script>
$(function () {
    var canExport = @json($canExport);
    var buttons = canExport
        ? $.fn.dataTable.defaults.buttons
        : [{ extend: 'colvis', text: LANG.col_vis, className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2' }];

    $('#compare_price_show_table').DataTable({
        fixedHeader: false,
        aaSorting: [],
        pageLength: 50,
        lengthMenu: [[10, 25, 50, 500, 1000, -1], [10, 25, 50, 500, 1000, 'All']],
        dom: '<"row margin-bottom-20 text-center no-print"<"col-sm-2"l><"col-sm-7"B><"col-sm-3"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>',
        buttons: buttons,
        fnDrawCallback: function () {
            __currency_convert_recursively($('#compare_price_show_table'));
        }
    });
});
</script>
@endsection
