@extends('smartstockinventory::layouts.master')
@section('page_title', 'Compare Price')
@section('module_content')
@can('stock_inventory.export')<input type="hidden" id="view_export_buttons">@endcan

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Compare Price
        <small>Fast SKU &amp; Qty Import — Real Purchase Price Comparison</small>
    </h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-4">
            <div class="form-group">
                <label for="compare_filter_location_id">{{ __('purchase.business_location') }}:</label>
                <select id="compare_filter_location_id" class="form-control select2" style="width:100%;">
                    <option value="all">{{ __('lang_v1.all') }}</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label for="compare_filter_date_range">{{ __('report.date_range') }}:</label>
                <input type="text"
                       id="compare_filter_date_range"
                       class="form-control"
                       placeholder="{{ __('lang_v1.select_a_date_range') }}"
                       readonly>
                <input type="hidden" id="compare_filter_start_date">
                <input type="hidden" id="compare_filter_end_date">
            </div>
        </div>
        <div class="col-md-4" style="padding-top:25px;">
            <button type="button" id="compare_filter_reset" class="btn btn-default btn-sm">
                <i class="fa fa-undo" aria-hidden="true"></i> Reset
            </button>
            <button type="button" id="compare_refresh_btn" class="btn btn-default btn-sm">
                <i class="fa fa-refresh" aria-hidden="true"></i> Refresh
            </button>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => 'Compare Price Lists'])
        @slot('tool')
            <div class="box-tools pull-right">
                @can('stock_inventory.create')
                    <a href="{{ ssi_route('ssi.compare_price.create') }}" class="btn btn-sm btn-primary">
                        <i class="fa fa-plus" aria-hidden="true"></i> New Compare Price
                    </a>
                @endcan
                <a href="{{ ssi_route('ssi.compare_price.template') }}" class="btn btn-sm btn-default">
                    <i class="fa fa-file-excel-o" aria-hidden="true"></i> Sample Template (SKU, Qty)
                </a>
            </div>
        @endslot

        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="compare_price_index_table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ref No</th>
                        <th>Title</th>
                        <th>Location Scope</th>
                        <th class="text-right">SKUs</th>
                        <th class="text-right">Total Qty</th>
                        <th>Created By</th>
                        <th>Notes</th>
                        <th class="no-export">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    @endcomponent
</section>
@endsection

@section('module_js')
<script>
$(function () {
    var indexUrl = @json(ssi_route('ssi.compare_price.index'));
    var canExport = @json(auth()->user()->can('stock_inventory.export') || auth()->user()->can('superadmin'));

    var $dateRange = $('#compare_filter_date_range');
    $dateRange.daterangepicker(
        $.extend({}, dateRangeSettings, { autoUpdateInput: false }),
        function (start, end) {
            $dateRange.val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            $('#compare_filter_start_date').val(start.format('YYYY-MM-DD'));
            $('#compare_filter_end_date').val(end.format('YYYY-MM-DD'));
            table.ajax.reload();
        }
    );
    $dateRange.on('cancel.daterangepicker', function () {
        $dateRange.val('');
        $('#compare_filter_start_date').val('');
        $('#compare_filter_end_date').val('');
        table.ajax.reload();
    });

    function prepareButtons(items) {
        return $.map(items || [], function (item) {
            var button = $.extend(true, {}, item);
            if (button.buttons) button.buttons = prepareButtons(button.buttons);
            if (button.extend === 'colvis') button.columns = ':not(.no-export)';
            else if (button.extend !== 'collection') button.exportOptions = $.extend(true, {}, button.exportOptions || {}, { columns: ':visible:not(.no-export)' });
            return button;
        });
    }

    var buttons = canExport
        ? prepareButtons($.fn.dataTable.defaults.buttons)
        : [{ extend: 'colvis', text: LANG.col_vis, columns: ':not(.no-export)', className: 'tw-dw-btn-xs tw-dw-btn tw-dw-btn-outline tw-my-2' }];

    var table = $('#compare_price_index_table').DataTable({
        processing: true,
        serverSide: false,
        fixedHeader: false,
        aaSorting: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 500, 1000, -1], [10, 25, 50, 500, 1000, 'All']],
        dom: '<"row margin-bottom-20 text-center no-print"<"col-sm-2"l><"col-sm-7"B><"col-sm-3"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>',
        buttons: buttons,
        ajax: {
            url: indexUrl,
            data: function (d) {
                d.location_id = $('#compare_filter_location_id').val();
                d.start_date = $('#compare_filter_start_date').val();
                d.end_date = $('#compare_filter_end_date').val();
            }
        },
        columns: [
            { data: 'compare_date', name: 'compare_date' },
            { data: 'ref_no', name: 'ref_no' },
            { data: 'title', name: 'title' },
            { data: 'location_name', name: 'location_name' },
            { data: 'item_count', name: 'item_count', className: 'text-right' },
            {
                data: 'total_qty',
                name: 'total_qty',
                className: 'text-right',
                render: function (val, type) {
                    return type === 'display' ? __currency_trans_from_en(Number(val || 0), false, false, __quantity_precision, true) : Number(val || 0);
                }
            },
            { data: 'created_by', name: 'created_by' },
            { data: 'notes', name: 'notes' },
            { data: 'actions', name: 'actions', className: 'no-export', orderable: false, searchable: false }
        ]
    });

    $('#compare_filter_location_id').on('change', function () { table.ajax.reload(); });
    $('#compare_refresh_btn').on('click', function () { table.ajax.reload(null, false); });
    $('#compare_filter_reset').on('click', function () {
        $('#compare_filter_location_id').val('all').trigger('change.select2');
        $dateRange.val('');
        $('#compare_filter_start_date').val('');
        $('#compare_filter_end_date').val('');
        table.search('').ajax.reload();
    });

    $(document).on('click', '.js-delete-compare-price', function () {
        var url = $(this).data('url');
        var ref = $(this).data('ref');
        swal({
            title: LANG.sure || 'Are you sure?',
            text: 'Delete price compare sheet ' + ref + '?',
            icon: 'warning',
            buttons: true,
            dangerMode: true
        }).then(function (confirmed) {
            if (!confirmed) return;
            $.ajax({
                url: url,
                type: 'DELETE',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            }).done(function () {
                toastr.success('Price Compare deleted.');
                table.ajax.reload(null, false);
            }).fail(function () {
                toastr.error('Unable to delete Price Compare.');
            });
        });
    });
});
</script>
@endsection
