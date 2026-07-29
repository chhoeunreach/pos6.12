@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sell List')
@section('module_content')
<style>
    #hr_sell_filter_box {
        border: 1px solid #e7edf3;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
        overflow: hidden;
        margin-bottom: 18px;
    }

    #hr_sell_filter_box .hr-sell-filter-toggle {
        background: #fff;
        cursor: pointer;
        user-select: none;
        padding: 14px 16px;
        border-bottom: 1px solid #eef2f6;
    }

    #hr_sell_filter_box .hr-sell-filter-toggle:focus {
        outline: 2px solid #78b7dc;
        outline-offset: -2px;
    }

    #hr_sell_filter_box .box-title {
        color: #6caed6;
        font-size: 18px;
        font-weight: 500;
    }

    #hr_sell_filter_box .box-body {
        padding: 22px 30px 24px;
    }

    #hr_sell_filter_box .form-group {
        margin-bottom: 16px;
    }

    #hr_sell_filter_box label {
        color: #111827;
        font-weight: 700;
        margin-bottom: 7px;
    }

    #hr_sell_filter_box .select2-container {
        width: 100% !important;
    }

    #hr_sell_filter_box .form-control,
    #hr_sell_filter_box .select2-selection {
        min-height: 42px;
        border-color: #d9e0e8;
        border-radius: 0;
        box-shadow: none;
    }

    #hr_sell_filter_box .input-group-addon {
        background: #f8fafc;
        border-color: #d9e0e8;
        color: #6caed6;
    }

    #hr_sell_filter_box .hr-sell-filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        min-height: 67px;
    }

    .hr-sell-action-btn {
        min-width: 64px;
    }

    .hr-sell-photo-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 8px;
    }

    .hr-sell-photo-thumb {
        width: 110px;
        height: 110px;
        padding: 0;
        border: 1px solid #d9e0e8;
        border-radius: 4px;
        overflow: hidden;
        background: #f8fafc;
        cursor: pointer;
    }

    .hr-sell-photo-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .hr-sell-photo-preview {
        display: block;
        max-width: 100%;
        max-height: 76vh;
        margin: 0 auto;
        object-fit: contain;
        background: #f8fafc;
    }

    .hr_sell_pos_photo_modal {
        z-index: 99999 !important;
    }

    .hr_sell_pos_photo_modal + .modal-backdrop {
        z-index: 99998 !important;
    }
</style>
@php($hasActiveFilters = request()->filled('search') || request()->filled('start_date') || request()->filled('end_date') || request()->filled('branch_name') || request()->filled('sell_type') || request()->filled('seller_key'))
<div class="box {{ $hasActiveFilters ? '' : 'collapsed-box' }}" id="hr_sell_filter_box">
<div class="box-header with-border hr-sell-filter-toggle" role="button" tabindex="0" aria-controls="hr_sell_filter_body" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
<h4 class="box-title"><i class="fa fa-filter"></i> Filters</h4>
<div class="box-tools pull-right">
<button type="button" class="btn btn-box-tool hr-sell-filter-button" title="{{ $hasActiveFilters ? 'Collapse filters' : 'Expand filters' }}"><i class="fa {{ $hasActiveFilters ? 'fa-minus' : 'fa-plus' }}"></i></button>
</div>
</div>
<div class="box-body" id="hr_sell_filter_body" @unless($hasActiveFilters) style="display:none;" @endunless>
<form method="get" action="{{ route('hr-sell.sales.index') }}">
<div class="row">
<div class="col-md-3"><div class="form-group"><label>Location / Branch:</label><select name="branch_name" class="form-control select2"><option value="">All</option>@foreach($hrBranches as $branch => $name)<option value="{{ $branch }}" @selected((string) request('branch_name') === (string) $branch)>{{ $name }}</option>@endforeach</select></div></div>
<div class="col-md-3"><div class="form-group"><label>Seller:</label><select name="seller_key" class="form-control select2"><option value="">All</option>@foreach($hrSellers as $key => $name)<option value="{{ $key }}" @selected((string) request('seller_key') === (string) $key)>{{ $name }}</option>@endforeach</select></div></div>
<div class="col-md-3"><div class="form-group"><label>Search:</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Invoice, customer, phone"></div></div>
<div class="col-md-3"><div class="form-group"><label>Date Range:</label><div class="input-group"><span class="input-group-addon"><i class="fa fa-calendar"></i></span><input type="text" id="hr_sell_date_range" class="form-control" readonly placeholder="{{ __('lang_v1.select_a_date_range') }}" value="{{ request('start_date') && request('end_date') ? request('start_date') . ' ~ ' . request('end_date') : '' }}"></div><input type="hidden" name="start_date" id="hr_sell_start_date" value="{{ request('start_date') }}"><input type="hidden" name="end_date" id="hr_sell_end_date" value="{{ request('end_date') }}"></div></div>
</div>
<div class="row">
<div class="col-md-3"><div class="form-group"><label>Sell Type:</label><select name="sell_type" class="form-control select2"><option value="">All</option>@foreach($hrSellTypes as $type => $name)<option value="{{ $type }}" @selected((string) request('sell_type') === (string) $type)>{{ $name }}</option>@endforeach</select></div></div>
<div class="col-md-9"><div class="hr-sell-filter-actions"><button class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button> <a class="btn btn-default" href="{{ route('hr-sell.sales.index') }}">Reset</a></div></div>
</div>
</form>
</div>
</div>

<div class="box box-success"><div class="box-header"><h4>POS HR Sell List</h4></div><div class="box-body table-responsive">
<table class="table table-bordered table-striped" id="pos_hr_sell_table"><thead><tr><th>Invoice</th><th>Date</th><th>Branch</th><th>Customer</th><th>Phone</th><th>Seller</th><th>Type</th><th>Total</th><th>Action</th></tr></thead><tbody>
@forelse($posHrSales as $sale)
<tr>
<td>{{ $sale->invoice_no }}</td>
<td>{{ $sale->created_at }}</td>
<td>{{ $sale->branch_name }}</td>
<td>{{ $sale->customer_name }}</td>
<td>{{ $sale->customer_phone }}</td>
<td>{{ $sale->staff_name ?: $sale->seller_name }} @if(! empty($sale->staff_code))<small class="text-muted">({{ $sale->staff_code }})</small>@endif</td>
<td>{{ $sale->service_type }}</td>
<td>{{ number_format((float) $sale->total_amount, 2) }}</td>
<td><button type="button" class="btn btn-xs btn-primary btn-modal hr-sell-action-btn" data-href="{{ route('hr-sell.sales.pos_detail', [$sale->id]) }}" data-container=".view_modal"><i class="fa fa-eye"></i> View</button></td>
</tr>
@empty
<tr><td colspan="9" class="text-center text-muted">No POS HR sell data found for selected filters.</td></tr>
@endforelse
</tbody></table>
<div class="clearfix">
<div class="pull-left text-muted">Showing {{ $posHrSales->firstItem() ?? 0 }} to {{ $posHrSales->lastItem() ?? 0 }} of {{ $posHrSales->total() }} POS HR sales</div>
<div class="pull-right">{{ $posHrSales->appends(request()->query())->links() }}</div>
</div>
</div></div>

<div class="modal fade hr_sell_pos_photo_modal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title hr-sell-photo-title">Photo</h4>
            </div>
            <div class="modal-body">
                <img class="hr-sell-photo-preview" src="" alt="HR sell photo">
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" class="btn btn-primary hr-sell-photo-open"><i class="fa fa-external-link"></i> Open</a>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
            </div>
        </div>
    </div>
</div>

@endsection
@section('module_js')
<script>
$(function(){
    $('.select2').select2();

    var dateRange = $('#hr_sell_date_range');
    var startDate = $('#hr_sell_start_date');
    var endDate = $('#hr_sell_end_date');

    if (dateRange.length && $.fn.daterangepicker) {
        var initialStart = startDate.val() ? moment(startDate.val(), 'YYYY-MM-DD') : moment();
        var initialEnd = endDate.val() ? moment(endDate.val(), 'YYYY-MM-DD') : moment();

        dateRange.daterangepicker({
            autoUpdateInput: false,
            startDate: initialStart,
            endDate: initialEnd,
            locale: {
                format: moment_date_format,
                cancelLabel: 'Clear'
            },
            ranges: {
                Today: [moment(), moment()],
                Yesterday: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end) {
            dateRange.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            startDate.val(start.format('YYYY-MM-DD'));
            endDate.val(end.format('YYYY-MM-DD'));
        });

        if (startDate.val() && endDate.val()) {
            dateRange.val(initialStart.format(moment_date_format) + ' ~ ' + initialEnd.format(moment_date_format));
        }

        dateRange.on('cancel.daterangepicker', function() {
            dateRange.val('');
            startDate.val('');
            endDate.val('');
        });
    }

    var hasActiveFilters = {{ $hasActiveFilters ? 'true' : 'false' }};
    var filterBox = $('#hr_sell_filter_box');
    var filterBody = $('#hr_sell_filter_body');
    var filterHeader = filterBox.find('.hr-sell-filter-toggle');
    var filterButton = filterBox.find('.hr-sell-filter-button');
    var filterIcon = filterButton.find('i.fa');
    var storedFilterState = localStorage.getItem('hr_sell_filters_expanded');

    function setFilterState(isExpanded) {
        filterBox.toggleClass('collapsed-box', !isExpanded);
        filterHeader.attr('aria-expanded', isExpanded ? 'true' : 'false');
        filterIcon.toggleClass('fa-minus', isExpanded).toggleClass('fa-plus', !isExpanded);
        filterButton.attr('title', isExpanded ? 'Collapse filters' : 'Expand filters');
        localStorage.setItem('hr_sell_filters_expanded', isExpanded ? '1' : '0');
    }

    function toggleFilter(forceExpanded) {
        var isExpanded = typeof forceExpanded === 'boolean'
            ? forceExpanded
            : filterBox.hasClass('collapsed-box');

        if (isExpanded) {
            filterBody.stop(true, true).slideDown(180);
        } else {
            filterBody.stop(true, true).slideUp(180);
        }

        setFilterState(isExpanded);
    }

    if (! hasActiveFilters && storedFilterState === '1') {
        toggleFilter(true);
    }

    filterHeader.on('click keydown', function(e) {
        if ($(e.target).closest('button, a, input, select, textarea, .select2').length) {
            return;
        }

        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
        }

        e.preventDefault();
        toggleFilter();
    });

    filterButton.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleFilter();
    });

    function initHrSellDataTable(selector, title) {
        if (! $.fn.DataTable || $.fn.DataTable.isDataTable(selector)) {
            return;
        }

        var buttons = [
            { extend: 'copy', text: 'Copy' },
            { extend: 'csv', text: 'Export CSV' },
            { extend: 'excel', text: 'Export Excel' },
            { extend: 'print', text: 'Print' },
            { extend: 'colvis', text: 'Column visibility' },
            {
                extend: 'pdf',
                text: 'Export PDF',
                orientation: 'landscape',
                pageSize: 'A4',
                title: title
            }
        ];

        $(selector).DataTable({
            paging: true,
            pageLength: parseInt(window.__default_datatable_page_entries || 25, 10),
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            searching: true,
            ordering: true,
            responsive: false,
            autoWidth: false,
            dom: '<"row"<"col-sm-3"l><"col-sm-6 text-center"B><"col-sm-3"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>',
            buttons: buttons,
            order: []
        });
    }

    initHrSellDataTable('#pos_hr_sell_table', 'POS HR Sell List');

    $(document).on('click', '.hr-sell-photo-thumb', function() {
        var photoUrl = $(this).data('photo-url');
        var fallbackUrl = $(this).data('photo-fallback-url') || photoUrl;
        var photoName = $(this).data('photo-name') || 'Photo';
        var photoModal = $('.hr_sell_pos_photo_modal');
        var preview = photoModal.find('.hr-sell-photo-preview');

        $('.view_modal').css('z-index', 1050);
        photoModal.find('.hr-sell-photo-title').text(photoName);
        photoModal.find('.hr-sell-photo-open').attr('href', photoUrl);
        preview
            .attr('src', photoUrl)
            .attr('data-fallback-url', fallbackUrl)
            .off('error.hrSellPhoto')
            .on('error.hrSellPhoto', function() {
                var fallback = $(this).attr('data-fallback-url');

                if (fallback && fallback !== $(this).attr('src')) {
                    $(this).attr('src', fallback);
                    photoModal.find('.hr-sell-photo-open').attr('href', fallback);
                }
            });

        photoModal.modal('show');
    });

    $('.hr_sell_pos_photo_modal').on('hidden.bs.modal', function() {
        $('.view_modal').css('z-index', '');
        $(this).find('.hr-sell-photo-preview').attr('src', '');
    });
});
</script>
@endsection
