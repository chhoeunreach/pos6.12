@extends('layouts.app')
@section('title', __('business.business_locations'))

@section('css')
    <style>
        .text-ellipsis {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
        }
    </style>
@endsection

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang( 'business.business_locations' )
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang( 'business.manage_your_business_locations' )</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'business.all_your_business_locations' )])
        @slot('tool')
            <div class="box-tools">
                <a class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary pull-right tw-mb-2 tw-ml-2"
                    href="{{ action([\App\Http\Controllers\BusinessLocationController::class, 'downloadTemplate']) }}">
                    <i class="fa fa-download"></i> @lang('lang_v1.download_template')
                </a>

                <button type="button"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary pull-right tw-mb-2 tw-ml-2"
                    data-toggle="modal" data-target="#bl_import_modal">
                    <i class="fa fa-upload"></i> @lang('lang_v1.import')
                </button>

                <button type="button"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary pull-right tw-mb-2 tw-ml-2"
                    data-toggle="modal" data-target="#bl_export_modal">
                    <i class="fa fa-file-excel-o"></i> @lang('lang_v1.export')
                </button>

                <button class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right tw-mb-2 btn-modal"
                    data-href="{{action([\App\Http\Controllers\BusinessLocationController::class, 'create'])}}" 
                    data-container=".location_add_modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg> @lang('messages.add')
                </button>
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="business_location_table">
                <thead>
                    <tr>
                        <th>@lang( 'invoice.name' )</th>
                        <th>@lang( 'lang_v1.location_id' )</th>
                        <th>@lang( 'business.landmark' )</th>
                        <th>@lang( 'business.city' )</th>
                        <th>@lang( 'business.zip_code' )</th>
                        <th>@lang( 'business.state' )</th>
                        <th>@lang( 'business.country' )</th>
                        <th>@lang( 'lang_v1.price_group' )</th>
                        <th>@lang( 'invoice.invoice_scheme' )</th>
                        <th>@lang('lang_v1.invoice_layout_for_pos')</th>
                        <th>@lang('lang_v1.invoice_layout_for_sale')</th>
                        <th>@lang( 'messages.action' )</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent

    <div class="modal fade location_add_modal" tabindex="-1" role="dialog" 
    	aria-labelledby="gridSystemModalLabel">
    </div>
    <div class="modal fade location_edit_modal" tabindex="-1" role="dialog" 
        aria-labelledby="gridSystemModalLabel">
    </div>

    <div class="modal fade" id="bl_import_modal" tabindex="-1" role="dialog" aria-labelledby="blImportModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="blImportModalLabel">@lang('lang_v1.import') @lang('business.business_locations')</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>@lang('messages.file'):</label>
                                <input type="file" class="form-control" id="bl_import_file" accept=".csv,.xlsx,.xls">
                                <p class="help-block">@lang('lang_v1.download_template') → fill → upload. (CSV/XLSX)</p>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>Import Mode:</label>
                                <select class="form-control" id="bl_import_mode">
                                    <option value="insert" selected>Insert Only</option>
                                    <option value="update">Update Existing</option>
                                    <option value="upsert">Insert &amp; Update</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div id="bl_preview_summary" class="well well-sm" style="display:none;"></div>

                    <div class="table-responsive" style="max-height: 320px; overflow:auto; display:none;" id="bl_preview_table_wrap">
                        <table class="table table-bordered table-striped" id="bl_preview_table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Location ID</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                    <th>Errors</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <input type="hidden" id="bl_import_token" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                    <button type="button" class="btn btn-info" id="bl_preview_btn"><i class="fa fa-eye"></i> Preview</button>
                    <button type="button" class="btn btn-primary" id="bl_confirm_import_btn" disabled><i class="fa fa-check"></i> Confirm Import</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bl_export_modal" tabindex="-1" role="dialog" aria-labelledby="blExportModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="blExportModalLabel">@lang('lang_v1.export') @lang('business.business_locations')</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Format:</label>
                                <select class="form-control" id="bl_export_format">
                                    <option value="csv" selected>CSV</option>
                                    <option value="xlsx">Excel (XLSX)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-top: 25px;">
                                <label>
                                    <input type="checkbox" id="bl_export_include_inactive" value="1">
                                    Include inactive locations
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                    <button type="button" class="btn btn-primary" id="bl_export_btn"><i class="fa fa-download"></i> Export</button>
                </div>
            </div>
        </div>
    </div>

</section>
<!-- /.content -->

@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        function resetImportPreview() {
            $('#bl_preview_summary').hide().html('');
            $('#bl_preview_table_wrap').hide();
            $('#bl_preview_table tbody').empty();
            $('#bl_import_token').val('');
            $('#bl_confirm_import_btn').prop('disabled', true);
        }

        $('#bl_import_modal').on('shown.bs.modal', function() {
            resetImportPreview();
        });

        $('#bl_import_modal').on('hidden.bs.modal', function() {
            $('#bl_import_file').val('');
            resetImportPreview();
        });

        $('#bl_preview_btn').click(function() {
            resetImportPreview();

            var fileInput = $('#bl_import_file')[0];
            if (!fileInput.files || !fileInput.files.length) {
                toastr.warning('Please choose a file first.');
                return;
            }

            var formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('file', fileInput.files[0]);
            formData.append('mode', $('#bl_import_mode').val());

            $.ajax({
                method: 'POST',
                url: '{{ action([\App\Http\Controllers\BusinessLocationController::class, 'importPreview']) }}',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    $('#bl_preview_btn').prop('disabled', true);
                    $('#bl_confirm_import_btn').prop('disabled', true);
                },
                success: function(result) {
                    $('#bl_preview_btn').prop('disabled', false);
                    if (!result || result.success !== true) {
                        toastr.error((result && result.msg) ? result.msg : LANG.something_went_wrong);
                        return;
                    }

                    $('#bl_import_token').val(result.token || '');

                    var s = result.summary || {};
                    var summaryHtml = '<b>Total:</b> ' + (s.total_rows || 0)
                        + ' | <b>New:</b> ' + (s.new_rows || 0)
                        + ' | <b>Existing:</b> ' + (s.existing_rows || 0)
                        + ' | <b>Skipped:</b> ' + (s.skipped_rows || 0)
                        + ' | <b>Errors:</b> ' + (s.error_rows || 0);
                    $('#bl_preview_summary').html(summaryHtml).show();

                    var rows = result.rows || [];
                    rows.forEach(function(r) {
                        var errors = (r.errors || []).join(', ');
                        var tr = '<tr>'
                            + '<td>' + (r.row_number || '') + '</td>'
                            + '<td>' + (r.name || '') + '</td>'
                            + '<td>' + (r.location_id || '') + '</td>'
                            + '<td>' + (r.status || '') + '</td>'
                            + '<td>' + (r.action || '') + '</td>'
                            + '<td>' + errors + '</td>'
                            + '</tr>';
                        $('#bl_preview_table tbody').append(tr);
                    });
                    $('#bl_preview_table_wrap').show();

                    if ((s.error_rows || 0) > 0) {
                        toastr.warning('Fix errors first, then preview again.');
                        $('#bl_confirm_import_btn').prop('disabled', true);
                    } else {
                        $('#bl_confirm_import_btn').prop('disabled', false);
                    }
                },
                error: function() {
                    $('#bl_preview_btn').prop('disabled', false);
                    toastr.error(LANG.something_went_wrong);
                }
            });
        });

        $('#bl_confirm_import_btn').click(function() {
            var token = $('#bl_import_token').val();
            if (!token) {
                toastr.warning('Please preview first.');
                return;
            }

            $.ajax({
                method: 'POST',
                url: '{{ action([\App\Http\Controllers\BusinessLocationController::class, 'importConfirm']) }}',
                dataType: 'json',
                data: {
                    _token: '{{ csrf_token() }}',
                    token: token,
                    mode: $('#bl_import_mode').val()
                },
                beforeSend: function() {
                    $('#bl_confirm_import_btn').prop('disabled', true);
                },
                success: function(result) {
                    if (result && result.success) {
                        toastr.success(result.msg);
                        $('#bl_import_modal').modal('hide');
                        if (typeof business_locations !== 'undefined') {
                            business_locations.ajax.reload();
                        }
                    } else {
                        toastr.error((result && result.msg) ? result.msg : LANG.something_went_wrong);
                        $('#bl_confirm_import_btn').prop('disabled', false);
                    }
                },
                error: function() {
                    toastr.error(LANG.something_went_wrong);
                    $('#bl_confirm_import_btn').prop('disabled', false);
                }
            });
        });

        $('#bl_export_btn').click(function() {
            var format = $('#bl_export_format').val();
            var include_inactive = $('#bl_export_include_inactive').is(':checked') ? 1 : 0;
            var url = '{{ action([\App\Http\Controllers\BusinessLocationController::class, 'export']) }}'
                + '?format=' + encodeURIComponent(format)
                + '&include_inactive=' + encodeURIComponent(include_inactive);
            window.location = url;
            $('#bl_export_modal').modal('hide');
        });
    });
</script>
@endsection
