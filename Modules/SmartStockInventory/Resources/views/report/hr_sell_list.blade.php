@extends('smartstockinventory::layouts.master')
@section('page_title', 'HR Sell List Report')
@section('module_content')

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">HR Sell List Report</h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('hr_sell_list_filter_branch',  __('lang_v1.branch') . ':') !!}
                {!! Form::select('hr_sell_list_filter_branch', $branches->prepend(__('lang_v1.all'), ''), null, ['class' => 'form-control select2', 'id' => 'hr_sell_list_filter_branch', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('hr_sell_list_filter_sell_type', 'Sell Type:') !!}
                {!! Form::select('hr_sell_list_filter_sell_type', $sell_types->prepend(__('lang_v1.all'), ''), null, ['class' => 'form-control select2', 'id' => 'hr_sell_list_filter_sell_type', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('hr_sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('hr_sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'hr_sell_list_filter_date_range', 'readonly']); !!}
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped ajax_view" id="hr_sell_list_report_table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>Staff</th>
                        <th>Branch</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th class="text-right">Total Amount</th>
                        <th>Sell Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>

<section id="receipt_section" class="print_section"></section>

<div class="modal fade hr_sell_list_photo_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title sell-list-photo-title">Photo</h4>
            </div>
            <div class="modal-body">
                <div class="sell-list-ocr-layout">
                    <div class="sell-list-ocr-image-panel">
                        <img class="sell-list-photo-preview" src="" alt="Sell Out photo">
                    </div>
                    <div class="sell-list-ocr-result-panel">
                        <div class="sell-list-ocr-status text-muted">
                            Open a photo to extract text.
                        </div>
                        <div class="progress sell-list-ocr-progress-wrap" style="display:none;">
                            <div class="progress-bar progress-bar-striped active sell-list-ocr-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width:0%;">
                                0%
                            </div>
                        </div>
                        <div class="form-group">
                            <label>OCR Result</label>
                            <textarea class="form-control sell-list-ocr-text" rows="8" readonly placeholder="Detected text will appear here..."></textarea>
                        </div>
                        <div class="sell-list-serial-section">
                            <label>Detected Serials</label>
                            <div class="sell-list-serials text-muted">No serials detected yet.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary sell-list-copy-text-btn">
                    <i class="fa fa-copy"></i> Copy Text
                </button>
                <button type="button" class="btn btn-success sell-list-copy-first-serial-btn" disabled>
                    <i class="fa fa-barcode"></i> Copy Serial
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
            </div>
        </div>
    </div>
</div>

@stop
@section('module_js')
<script src="{{ asset('js/sell-out-ocr.js?v=' . $asset_v . '&m=' . (file_exists(public_path('js/sell-out-ocr.js')) ? filemtime(public_path('js/sell-out-ocr.js')) : time())) }}"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $('#hr_sell_list_filter_date_range').daterangepicker(
            $.extend({}, dateRangeSettings, {
                startDate: moment(),
                endDate: moment()
            }),
            function (start, end) {
                $('#hr_sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                hr_sell_list_report_table.ajax.reload();
            }
        );
        $('#hr_sell_list_filter_date_range').val(moment().format(moment_date_format) + ' ~ ' + moment().format(moment_date_format));

        $('#hr_sell_list_filter_date_range').on('cancel.daterangepicker', function() {
            $('#hr_sell_list_filter_date_range').val('');
            hr_sell_list_report_table.ajax.reload();
        });

        hr_sell_list_report_table = $('#hr_sell_list_report_table').DataTable({
            processing: true,
            serverSide: true,
            fixedHeader: false,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: "{{ ssi_route('ssi.report.hr_sell_list') }}",
                data: function(d) {
                    if ($('#hr_sell_list_filter_date_range').val()) {
                        d.start_date = $('#hr_sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        d.end_date = $('#hr_sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    d.branch_name = $('#hr_sell_list_filter_branch').val();
                    d.sell_type = $('#hr_sell_list_filter_sell_type').val();
                    d = __datatable_ajax_callback(d);
                }
            },
            columns: [
                { data: 'created_at', name: 'sor.created_at', type: 'date' },
                { data: 'invoice_no', name: 'sor.invoice_no', type: 'text' },
                { data: 'staff_name', name: 'staff_name', type: 'text' },
                { data: 'branch_name', name: 'sor.branch_name', type: 'text' },
                { data: 'customer_name', name: 'sor.customer_name', type: 'text' },
                { data: 'customer_phone', name: 'sor.customer_phone', type: 'text' },
                { data: 'total_amount', name: 'sor.total_amount', className: 'text-right' },
                { data: 'service_type', name: 'sor.service_type', type: 'text' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            fnDrawCallback: function() {
                __currency_convert_recursively($('#hr_sell_list_report_table'));
            }
        });

        $(document).on('change', '#hr_sell_list_filter_branch, #hr_sell_list_filter_sell_type, #hr_sell_list_filter_date_range', function() {
            hr_sell_list_report_table.ajax.reload();
        });

        $(document).on('change', '#hr_sell_list_filter_branch', function() {
            var branch = $(this).val();
            $.ajax({
                url: "{{ ssi_route('ssi.report.hr_sell_list_service_types') }}",
                data: { branch_name: branch },
                success: function(types) {
                    var $sellType = $('#hr_sell_list_filter_sell_type');
                    $sellType.empty();
                    $sellType.append($('<option>', { value: '', text: '{{ __('lang_v1.all') }}' }));
                    $.each(types, function(i, type) {
                        $sellType.append($('<option>', { value: type, text: type }));
                    });
                    $sellType.trigger('change.select2');
                }
            });
        });

        $(document).on('click', '.sell-list-photo-thumb', function(){
            var photoUrl = $(this).data('photo-url');
            var fallbackUrl = $(this).data('photo-fallback-url') || photoUrl;
            var photoName = $(this).data('photo-name') || 'Photo';
            var $modal = $('.hr_sell_list_photo_modal');

            $modal.find('.sell-list-photo-title').text(photoName);
            $modal.find('.sell-list-photo-preview')
                .attr('src', photoUrl)
                .attr('data-fallback-url', fallbackUrl);
            $modal.modal('show');
        });
    });
</script>
@endsection
