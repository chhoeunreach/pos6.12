@extends('smartstockinventory::layouts.master')
@section('page_title', __('report.stock_transfer_report'))
@section('module_content')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('report.stock_transfer_report')</h1>
</section>

<section class="content no-print">
    <div class="row">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_location_from', __('lang_v1.location_from') . ':') !!}
                    {!! Form::select('st_report_location_from', $business_locations, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'id' => 'st_report_location_from',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_location_to', __('lang_v1.location_to') . ':') !!}
                    {!! Form::select('st_report_location_to', $business_locations, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'id' => 'st_report_location_to',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_sender', __('lang_v1.added_by') . ':') !!}
                    {!! Form::select('st_report_sender[]', $users, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'id' => 'st_report_sender',
                        'multiple',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('st_report_date_range', __('report.date_range') . ':') !!}
                    {!! Form::text('st_report_date_range', null, [
                        'class' => 'form-control',
                        'id' => 'st_report_date_range',
                        'readonly',
                        'placeholder' => __('report.date_range'),
                    ]) !!}
                </div>
            </div>
        @endcomponent
    </div>

    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('report.stock_transfer_report')])
                @slot('tool')
                    <div class="box-tools">
                        <button type="button" class="btn btn-primary btn-xs" id="copy_table_btn">
                            <i class="fa fa-copy"></i> @lang('lang_v1.copy')
                        </button>
                        <a class="btn btn-xs btn-success" id="export_csv_btn">
                            <i class="fa fa-download"></i> CSV
                        </a>
                    </div>
                @endslot
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="stock_transfer_report_table">
                        <thead>
                            <tr>
                                <th>@lang('messages.date')</th>
                                <th>@lang('lang_v1.lot_number')</th>
                                <th>@lang('product.sku')</th>
                                <th>@lang('sale.product')</th>
                                <th>@lang('lang_v1.quantity')</th>
                                <th>@lang('lang_v1.location_from')</th>
                                <th>@lang('lang_v1.location_to')</th>
                                <th>@lang('sale.invoice_no')</th>
                                <th>@lang('lang_v1.added_by')</th>
                                <th>@lang('purchase.additional_notes')</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th>@lang('lang_v1.total')</th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>

@stop
@section('module_js')
<script>
$(document).ready(function() {
    if ($('#st_report_date_range').length == 1) {
        var drpSettings = $.extend(true, {}, dateRangeSettings, {
            startDate: moment(),
            endDate: moment()
        });
        $('#st_report_date_range').daterangepicker(drpSettings, function(start, end) {
            $('#st_report_date_range').val(
                start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
            );
            stock_transfer_report_table.ajax.reload();
        });
        $('#st_report_date_range').val(
            moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format)
        );
        $('#st_report_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#st_report_date_range').val('');
            stock_transfer_report_table.ajax.reload();
        });
    }

    if ($.fn.DataTable.isDataTable('#stock_transfer_report_table')) {
        $('#stock_transfer_report_table').DataTable().destroy();
    }
    stock_transfer_report_table = $('#stock_transfer_report_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[0, 'desc']],
        ajax: {
            url: "{{ ssi_route('ssi.report.stock_transfer') }}",
            data: function(d) {
                d.location_from_id = $('#st_report_location_from').val();
                d.location_to_id = $('#st_report_location_to').val();
                d.sender_id = $('#st_report_sender').val();
                if ($('#st_report_date_range').val()) {
                    d.start_date = $('#st_report_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    d.end_date = $('#st_report_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                }
                d = __datatable_ajax_callback(d);
            }
        },
        columns: [
            { data: 'transaction_date', name: 'transaction_date' },
            { data: 'lot_number', name: 'lot_number' },
            { data: 'sku', name: 'sku' },
            { data: 'product_name', name: 'product_name' },
            { data: 'qty', name: 'qty', searchable: false },
            { data: 'location_from', name: 'location_from' },
            { data: 'location_to', name: 'location_to' },
            { data: 'invoice', name: 'invoice' },
            { data: 'sender_by', name: 'sender_by' },
            { data: 'note', name: 'note' },
        ],
        footerCallback: function() {
            var api = this.api();
            var total = api.column(4, { page: 'current' }).data().reduce(function(a, b) {
                return parseFloat(a) + parseFloat(b);
            }, 0);
            $(api.column(4).footer()).html(total.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        }
    });

    $(document).on('change', '#st_report_location_from, #st_report_location_to, #st_report_sender', function() {
        stock_transfer_report_table.ajax.reload();
    });

    $('#copy_table_btn').click(function() {
        var tableData = '';
        $('#stock_transfer_report_table thead tr').each(function() {
            $(this).find('th').each(function() {
                tableData += $(this).text() + '\t';
            });
            tableData += '\n';
        });
        $('#stock_transfer_report_table tbody tr').each(function() {
            $(this).find('td').each(function() {
                tableData += $(this).text() + '\t';
            });
            tableData += '\n';
        });
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(tableData).select();
        document.execCommand('copy');
        $temp.remove();
        toastr.success('@lang("lang_v1.copied")');
    });

    $('#export_csv_btn').click(function() {
        var csvData = '\uFEFF';
        $('#stock_transfer_report_table thead tr').each(function() {
            $(this).find('th').each(function() {
                csvData += '"' + $(this).text() + '",';
            });
            csvData += '\n';
        });
        $('#stock_transfer_report_table tbody tr').each(function() {
            $(this).find('td').each(function() {
                csvData += '"' + $(this).text() + '",';
            });
            csvData += '\n';
        });
        var blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'stock_transfer_report.csv';
        link.click();
    });
});
</script>
@endsection
