@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sell Reports')
@section('module_content')
@php
    $currentUser = auth()->user();
    $canEditReport = $currentUser->can('hr_sell.report.edit') || $currentUser->can('hr_sell.update') || $currentUser->can('superadmin') || $currentUser->can('business_settings.access');
    $canDeleteReport = $currentUser->can('hr_sell.report.delete') || $currentUser->can('superadmin') || $currentUser->can('business_settings.access');
    $hrReportPerPageOptions = ['25' => '25', '50' => '50', '100' => '100', '200' => '200', '500' => '500', 'all' => 'All'];
    $hrReportPerPage = array_key_exists((string) request('hr_report_per_page', '50'), $hrReportPerPageOptions) ? (string) request('hr_report_per_page', '50') : '50';
@endphp
<style>
    #hr_sell_report_filter_box {
        border: 1px solid #e7edf3;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
        overflow: hidden;
        margin-bottom: 18px;
    }

    #hr_sell_report_filter_box .hr-sell-report-filter-toggle {
        background: #fff;
        cursor: pointer;
        user-select: none;
        padding: 14px 16px;
        border-bottom: 1px solid #eef2f6;
    }

    #hr_sell_report_filter_box .box-title {
        color: #6caed6;
        font-size: 18px;
        font-weight: 500;
    }

    #hr_sell_report_filter_box .box-body {
        padding: 22px 30px 24px;
    }

    #hr_sell_report_filter_box label {
        color: #111827;
        font-weight: 700;
        margin-bottom: 7px;
    }

    #hr_sell_report_filter_box .select2-container {
        width: 100% !important;
    }

    #hr_sell_report_filter_box .form-control,
    #hr_sell_report_filter_box .select2-selection {
        min-height: 42px;
        border-color: #d9e0e8;
        border-radius: 0;
        box-shadow: none;
    }

    #hr_sell_report_filter_box .input-group-addon {
        background: #f8fafc;
        border-color: #d9e0e8;
        color: #6caed6;
    }

    .hr-sell-report-actions {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        min-height: 67px;
    }

    .hr-sell-report-summary .small-box {
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, 0.08);
    }

    .hr-sell-line-list {
        margin: 0;
        padding-left: 16px;
        min-width: 220px;
    }

    .hr-sell-line-list li {
        margin-bottom: 3px;
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

    .hr_sell_report_photo_modal {
        z-index: 99999 !important;
    }

    .hr_sell_report_photo_modal + .modal-backdrop {
        z-index: 99998 !important;
    }
</style>

@php($hasActiveFilters = request()->filled('search') || request()->filled('start_date') || request()->filled('end_date') || request()->filled('branch_name') || request()->filled('department_id') || request()->filled('sell_type') || request()->filled('seller_key'))
@php($selectedBranchNames = collect((array) request('branch_name', []))->map(fn ($branchName) => (string) $branchName)->all())
@php($selectedDepartmentIds = collect((array) request('department_id', []))->map(fn ($departmentId) => (string) $departmentId)->all())
<div class="box {{ $hasActiveFilters ? '' : 'collapsed-box' }}" id="hr_sell_report_filter_box">
    <div class="box-header with-border hr-sell-report-filter-toggle" role="button" tabindex="0" aria-controls="hr_sell_report_filter_body" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
        <h4 class="box-title"><i class="fa fa-filter"></i> Filters</h4>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool hr-sell-report-filter-button" title="{{ $hasActiveFilters ? 'Collapse filters' : 'Expand filters' }}"><i class="fa {{ $hasActiveFilters ? 'fa-minus' : 'fa-plus' }}"></i></button>
        </div>
    </div>
    <div class="box-body" id="hr_sell_report_filter_body" @unless($hasActiveFilters) style="display:none;" @endunless>
        <form method="get" action="{{ route('hr-sell.reports.index') }}">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Location / Branch:</label>
                        <select name="branch_name[]" class="form-control select2 js-hr-multi-select js-branch-filter" multiple data-placeholder="All">
                            @foreach($hrBranches as $branch => $name)
                                <option value="{{ $branch }}" @selected(in_array((string) $branch, $selectedBranchNames, true))>{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="btn-group btn-group-xs" style="margin-top:5px;">
                            <button type="button" class="btn btn-default js-select-all-options">Select all</button>
                            <button type="button" class="btn btn-default js-clear-all-options">Clear all</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Seller:</label>
                        <select name="seller_key" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrSellers as $key => $name)
                                <option value="{{ $key }}" @selected((string) request('seller_key') === (string) $key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Department:</label>
                        <select name="department_id[]" class="form-control select2 js-hr-multi-select" multiple data-placeholder="All">
                            @foreach($hrDepartments as $departmentId => $departmentName)
                                <option value="{{ $departmentId }}" @selected(in_array((string) $departmentId, $selectedDepartmentIds, true))>{{ $departmentName }}</option>
                            @endforeach
                        </select>
                        <div class="btn-group btn-group-xs" style="margin-top:5px;">
                            <button type="button" class="btn btn-default js-select-all-options">Select all</button>
                            <button type="button" class="btn btn-default js-clear-all-options">Clear all</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Search:</label>
                        <input name="search" class="form-control" value="{{ request('search') }}" placeholder="Invoice, customer, phone, IMEI">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range:</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                            <input type="text" id="hr_sell_report_date_range" class="form-control" readonly placeholder="{{ __('lang_v1.select_a_date_range') }}" value="{{ request('start_date') && request('end_date') ? request('start_date') . ' ~ ' . request('end_date') : '' }}">
                        </div>
                        <input type="hidden" name="start_date" id="hr_sell_report_start_date" value="{{ request('start_date') }}">
                        <input type="hidden" name="end_date" id="hr_sell_report_end_date" value="{{ request('end_date') }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Sell Type:</label>
                        <select name="sell_type" class="form-control select2">
                            <option value="">All</option>
                            @foreach($hrSellTypes as $type => $name)
                                <option value="{{ $type }}" @selected((string) request('sell_type') === (string) $type)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="hr-sell-report-actions">
                        <button class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                        <a class="btn btn-default" href="{{ route('hr-sell.reports.index') }}">Reset</a>
                        <a class="btn btn-success" href="{{ route('hr-sell.reports.export', request()->all()) }}"><i class="fa fa-download"></i> Export All Filtered</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row hr-sell-report-summary">
    @foreach([
        'sale_count' => ['Sales', 'bg-aqua', 0],
        'sale_total' => ['Total Amount', 'bg-green', 2],
        'total_qty' => ['Total Qty', 'bg-yellow', 2],
        'customer_count' => ['Customers', 'bg-purple', 0],
        'average_sale' => ['Average Sale', 'bg-blue', 2],
    ] as $key => $meta)
        <div class="col-md-2 col-sm-4">
            <div class="small-box {{ $meta[1] }}">
                <div class="inner">
                    <h3>{{ number_format((float) ($summary[$key] ?? 0), $meta[2]) }}</h3>
                    <p>{{ $meta[0] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="box box-success">
    <div class="box-header"><h4>HR Sell Report</h4></div>
    <div class="box-body table-responsive">
        <div class="clearfix" style="margin-bottom: 10px;">
            <form method="get" action="{{ route('hr-sell.reports.index') }}" class="form-inline pull-left">
                @foreach(request()->except(['hr_report_per_page', 'hr_report_page']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $item)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="text-muted" for="hr_report_per_page" style="margin-right: 5px;">Show</label>
                <select name="hr_report_per_page" id="hr_report_per_page" class="form-control input-sm" onchange="this.form.submit()">
                    @foreach($hrReportPerPageOptions as $value => $label)
                        <option value="{{ $value }}" {{ $hrReportPerPage === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="text-muted" style="margin-left: 5px;">records</span>
            </form>
        </div>
        <table class="table table-bordered table-striped" id="hr_sell_report_table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Branch</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Seller</th>
                    <th>Type</th>
                    <th>Products</th>
                    <th>Serial / IMEI</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->invoice_no }}</td>
                        <td>{{ $row->created_at }}</td>
                        <td>{{ $row->branch_name }}</td>
                        <td>{{ $row->customer_name ?: '-' }}</td>
                        <td>{{ $row->customer_phone ?: '-' }}</td>
                        <td>{{ $row->staff_name ?: $row->seller_name }} @if(! empty($row->staff_code))<small class="text-muted">({{ $row->staff_code }})</small>@endif</td>
                        <td>{{ $row->service_type_label ?? ($row->service_type ?: '-') }}</td>
                        <td>
                            @php($products = collect(explode('|||', (string) $row->product_names))->filter()->take(5))
                            @if($products->isNotEmpty())
                                <ul class="hr-sell-line-list">
                                    @foreach($products as $product)
                                        <li>{{ $product }}</li>
                                    @endforeach
                                </ul>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @php($serials = collect(explode('|||', (string) $row->serial_numbers))->filter()->take(5))
                            @if($serials->isNotEmpty())
                                <ul class="hr-sell-line-list">
                                    @foreach($serials as $serial)
                                        <li>{{ $serial }}</li>
                                    @endforeach
                                </ul>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-right">{{ number_format((float) $row->total_qty, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $row->total_amount, 2) }}</td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-xs btn-primary btn-modal" data-href="{{ route('hr-sell.sales.pos_detail', [$row->id]) }}" data-container=".view_modal"><i class="fa fa-eye"></i> View</button>
                                @if($canEditReport)
                                    <button type="button" class="btn btn-xs btn-info btn-modal" data-href="{{ route('hr-sell.reports.edit', [$row->id]) }}" data-container=".view_modal"><i class="fa fa-edit"></i> Edit</button>
                                @endif
                                @if($canDeleteReport)
                                    <button type="button" class="btn btn-xs btn-danger js-delete-hr-sell-report" data-href="{{ route('hr-sell.reports.destroy', [$row->id]) }}"><i class="fa fa-trash"></i> Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted">No HR sell report data found for selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="clearfix">
            <div class="pull-left text-muted">Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} HR sell records</div>
            <div class="pull-right">{{ $rows->appends(request()->query())->links() }}</div>
        </div>
    </div>
</div>

<div class="modal fade hr_sell_report_photo_modal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
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
$(function() {
    $('.select2').select2();

    $(document).on('click', '.js-select-all-options', function() {
        var select = $(this).closest('.form-group').find('select.js-hr-multi-select');
        select.find('option').prop('selected', true);
        select.trigger('change');
    });

    $(document).on('click', '.js-clear-all-options', function() {
        var select = $(this).closest('.form-group').find('select.js-hr-multi-select');
        select.val(null).trigger('change');
    });

    $('.js-branch-filter').on('change', function() {
        var form = $(this).closest('form');
        form.find('select[name="department_id[]"]').val(null);
        form.submit();
    });

    var dateRange = $('#hr_sell_report_date_range');
    var startDate = $('#hr_sell_report_start_date');
    var endDate = $('#hr_sell_report_end_date');

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
    var filterBox = $('#hr_sell_report_filter_box');
    var filterBody = $('#hr_sell_report_filter_body');
    var filterHeader = filterBox.find('.hr-sell-report-filter-toggle');
    var filterButton = filterBox.find('.hr-sell-report-filter-button');
    var filterIcon = filterButton.find('i.fa');
    var storedFilterState = localStorage.getItem('hr_sell_report_filters_expanded');

    function setFilterState(isExpanded) {
        filterBox.toggleClass('collapsed-box', !isExpanded);
        filterHeader.attr('aria-expanded', isExpanded ? 'true' : 'false');
        filterIcon.toggleClass('fa-minus', isExpanded).toggleClass('fa-plus', !isExpanded);
        filterButton.attr('title', isExpanded ? 'Collapse filters' : 'Expand filters');
        localStorage.setItem('hr_sell_report_filters_expanded', isExpanded ? '1' : '0');
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

    if ($.fn.DataTable && ! $.fn.DataTable.isDataTable('#hr_sell_report_table')) {
        $('#hr_sell_report_table').DataTable({
            paging: false,
            searching: false,
            info: false,
            ordering: true,
            responsive: false,
            autoWidth: false,
            dom: '<"row"<"col-sm-12 text-center"B>>rt',
            buttons: [
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
                    title: 'HR Sell Report'
                }
            ],
            order: []
        });
    }

    $(document).on('click', '.hr-sell-photo-thumb', function() {
        var photoUrl = $(this).data('photo-url');
        var fallbackUrl = $(this).data('photo-fallback-url') || photoUrl;
        var photoName = $(this).data('photo-name') || 'Photo';
        var photoModal = $('.hr_sell_report_photo_modal');
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

    $('.hr_sell_report_photo_modal').on('hidden.bs.modal', function() {
        $('.view_modal').css('z-index', '');
        $(this).find('.hr-sell-photo-preview').attr('src', '');
    });

    $(document).on('click', '.js-delete-hr-sell-report', function(e) {
        e.preventDefault();

        var href = $(this).data('href');
        var deleteReport = function() {
            $.ajax({
                url: href,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    _method: 'DELETE'
                },
                success: function(result) {
                    if (result.success) {
                        if (typeof toastr !== 'undefined') {
                            toastr.success(result.msg);
                        }

                        window.location.reload();
                    } else if (typeof toastr !== 'undefined') {
                        toastr.error(result.msg || 'Unable to delete HR sell report');
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to delete HR sell report';

                    if (typeof toastr !== 'undefined') {
                        toastr.error(message);
                    } else {
                        alert(message);
                    }
                }
            });
        };

        if (typeof swal !== 'undefined') {
            swal({
                title: 'Delete HR sell report?',
                text: 'This will delete the report, product lines, and photos from the HR sell database.',
                icon: 'warning',
                buttons: true,
                dangerMode: true
            }).then(function(confirmDelete) {
                if (confirmDelete) {
                    deleteReport();
                }
            });
        } else if (confirm('Delete this HR sell report?')) {
            deleteReport();
        }
    });
});
</script>
@endsection
