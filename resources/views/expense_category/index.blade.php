@extends('layouts.app')
@section('title', __('expense.expense_categories'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang( 'expense.expense_categories' )
        <small  class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang( 'expense.manage_your_expense_categories' )</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'expense.all_your_expense_categories' )])
        @slot('tool')
            <div class="box-tools">
                
                <button type="button"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary pull-right tw-mb-2 tw-ml-2"
                    data-toggle="modal" data-target="#exp_cat_import_modal">
                    <i class="fa fa-upload"></i> @lang('lang_v1.import')
                </button>

                <button type="button"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary pull-right tw-mb-2 tw-ml-2"
                    data-toggle="modal" data-target="#exp_cat_export_modal">
                    <i class="fa fa-file-excel-o"></i> @lang('lang_v1.export')
                </button>

                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal pull-right"
                    data-href="{{action([\App\Http\Controllers\ExpenseCategoryController::class, 'create'])}}" 
                    data-container=".expense_category_modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg> @lang('messages.add')
                </a>
            </div>
        @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="expense_category_table">
                <thead>
                    <tr>
                        <th>@lang( 'expense.category_name' )</th>
                        <th>@lang( 'expense.category_code' )</th>
                        <th>@lang( 'messages.action' )</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent

    <div class="modal fade expense_category_modal" tabindex="-1" role="dialog" 
    	aria-labelledby="gridSystemModalLabel">
    </div>

    <div class="modal fade" id="exp_cat_import_modal" tabindex="-1" role="dialog" aria-labelledby="expCatImportModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="expCatImportModalLabel">@lang('lang_v1.import') @lang('expense.expense_categories')</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>@lang('messages.file'):</label>
                                <input type="file" class="form-control" id="exp_cat_import_file" accept=".csv,.xlsx,.xls">
                                <p class="help-block">
                                    <a href="{{ action([\App\Http\Controllers\ExpenseCategoryController::class, 'downloadTemplate']) }}">
                                        <i class="fa fa-download"></i> @lang('lang_v1.download_template')
                                    </a> → fill → upload. (CSV/XLSX)
                                </p>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div id="exp_cat_preview_summary" class="well well-sm" style="display:none;"></div>

                    <div class="table-responsive" style="max-height: 320px; overflow:auto; display:none;" id="exp_cat_preview_table_wrap">
                        <table class="table table-bordered table-striped" id="exp_cat_preview_table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('expense.category_name')</th>
                                    <th>@lang('expense.category_code')</th>
                                    <th>@lang('expense.category_parent')</th>
                                    <th>Status</th>
                                    <th>Errors</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <input type="hidden" id="exp_cat_import_token" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                    <button type="button" class="btn btn-info" id="exp_cat_preview_btn"><i class="fa fa-eye"></i> Preview</button>
                    <button type="button" class="btn btn-primary" id="exp_cat_confirm_import_btn" disabled><i class="fa fa-check"></i> Confirm Import</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="exp_cat_export_modal" tabindex="-1" role="dialog" aria-labelledby="expCatExportModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="expCatExportModalLabel">@lang('lang_v1.export') @lang('expense.expense_categories')</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>Format:</label>
                                <select class="form-control" id="exp_cat_export_format">
                                    <option value="csv" selected>CSV</option>
                                    <option value="xlsx">Excel (XLSX)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                    <button type="button" class="btn btn-primary" id="exp_cat_export_btn"><i class="fa fa-download"></i> Export</button>
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
            $('#exp_cat_preview_summary').hide().html('');
            $('#exp_cat_preview_table_wrap').hide();
            $('#exp_cat_preview_table tbody').empty();
            $('#exp_cat_import_token').val('');
            $('#exp_cat_confirm_import_btn').prop('disabled', true);
        }

        $('#exp_cat_import_modal').on('shown.bs.modal', function() {
            resetImportPreview();
        });

        $('#exp_cat_import_modal').on('hidden.bs.modal', function() {
            $('#exp_cat_import_file').val('');
            resetImportPreview();
        });

        $('#exp_cat_import_file').on('change', function() {
            resetImportPreview();
        });

        $('#exp_cat_preview_btn').click(function() {
            resetImportPreview();

            var fileInput = $('#exp_cat_import_file')[0];
            if (!fileInput.files || !fileInput.files.length) {
                toastr.warning('Please choose a file first.');
                return;
            }

            var formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('file', fileInput.files[0]);

            $.ajax({
                method: 'POST',
                url: '{{ action([\App\Http\Controllers\ExpenseCategoryController::class, 'importPreview']) }}',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    $('#exp_cat_preview_btn').prop('disabled', true);
                    $('#exp_cat_confirm_import_btn').prop('disabled', true);
                },
                success: function(result) {
                    $('#exp_cat_preview_btn').prop('disabled', false);
                    if (!result || result.success !== true) {
                        toastr.error((result && result.msg) ? result.msg : LANG.something_went_wrong);
                        return;
                    }

                    $('#exp_cat_import_token').val(result.token || '');

                    var s = result.summary || {};
                    var summaryHtml = '<b>Total:</b> ' + (s.total_rows || 0)
                        + ' | <b>New:</b> ' + (s.new_rows || 0)
                        + ' | <b>Existing:</b> ' + (s.existing_rows || 0)
                        + ' | <b>Errors:</b> ' + (s.error_rows || 0);
                    $('#exp_cat_preview_summary').html(summaryHtml).show();

                    var rows = result.rows || [];
                    rows.forEach(function(r) {
                        var errors = (r.errors || []).join(', ');
                        var tr = '<tr>'
                            + '<td>' + (r.row_number || '') + '</td>'
                            + '<td>' + (r.name || '') + '</td>'
                            + '<td>' + (r.code || '') + '</td>'
                            + '<td>' + (r.parent_category || '') + '</td>'
                            + '<td>' + (r.status || '') + '</td>'
                            + '<td>' + errors + '</td>'
                            + '</tr>';
                        $('#exp_cat_preview_table tbody').append(tr);
                    });
                    $('#exp_cat_preview_table_wrap').show();

                    if ((s.error_rows || 0) > 0) {
                        toastr.warning('Fix errors first, then preview again.');
                        $('#exp_cat_confirm_import_btn').prop('disabled', true);
                    } else {
                        $('#exp_cat_confirm_import_btn').prop('disabled', false);
                    }
                },
                error: function() {
                    $('#exp_cat_preview_btn').prop('disabled', false);
                    toastr.error(LANG.something_went_wrong);
                }
            });
        });

        $('#exp_cat_confirm_import_btn').click(function() {
            var token = $('#exp_cat_import_token').val();
            if (!token) {
                toastr.warning('Please preview first.');
                return;
            }

            $.ajax({
                method: 'POST',
                url: '{{ action([\App\Http\Controllers\ExpenseCategoryController::class, 'importConfirm']) }}',
                dataType: 'json',
                data: {
                    _token: '{{ csrf_token() }}',
                    token: token
                },
                beforeSend: function() {
                    $('#exp_cat_confirm_import_btn').prop('disabled', true);
                },
                success: function(result) {
                    if (result && result.success) {
                        toastr.success(result.msg);
                        $('#exp_cat_import_modal').modal('hide');
                        if (typeof expense_category_table !== 'undefined') {
                            expense_category_table.ajax.reload();
                        }
                    } else {
                        toastr.error((result && result.msg) ? result.msg : LANG.something_went_wrong);
                        $('#exp_cat_confirm_import_btn').prop('disabled', false);
                    }
                },
                error: function() {
                    toastr.error(LANG.something_went_wrong);
                    $('#exp_cat_confirm_import_btn').prop('disabled', false);
                }
            });
        });

        $('#exp_cat_export_btn').click(function() {
            var format = $('#exp_cat_export_format').val();
            var url = '{{ action([\App\Http\Controllers\ExpenseCategoryController::class, 'export']) }}'
                + '?format=' + encodeURIComponent(format);
            window.location = url;
            $('#exp_cat_export_modal').modal('hide');
        });
    });
</script>
@endsection
