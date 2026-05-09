@extends('layouts.app')
@section('title', __( 'user.users' ))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang( 'user.users' )
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang( 'user.manage_users' )</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'user.all_users' )])
        @can('user.create')
            @slot('tool')
                <div class="box-tools">
                    @if(!empty($can_user_import_export))
                        <a class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary tw-mr-2" href="{{ action([\App\Http\Controllers\ManageUserController::class, 'downloadTemplate']) }}">
                            <i class="fa fa-download"></i> @lang('lang_v1.download_template')
                        </a>
                        <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary tw-mr-2" data-toggle="modal" data-target="#users_import_modal">
                            <i class="fa fa-upload"></i> @lang('lang_v1.import')
                        </button>
                        <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary tw-mr-2" data-toggle="modal" data-target="#users_export_modal">
                            <i class="fa fa-file-excel-o"></i> @lang('lang_v1.export')
                        </button>
                    @endif
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full" href="{{action([\App\Http\Controllers\ManageUserController::class, 'create'])}}">
                        <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>                        @lang( 'messages.add' )
                    </a>
                 </div>
            @endslot
        @endcan
        @can('user.view')
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="users_table">
                    <thead>
                        <tr>
                            <th>@lang( 'business.username' )</th>
                            <th>@lang( 'user.name' )</th>
                            <th>@lang( 'user.role' )</th>
                            <th>@lang( 'business.email' )</th>
                            <th>@lang( 'messages.action' )</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcan
    @endcomponent

    <div class="modal fade user_modal" tabindex="-1" role="dialog" 
    	aria-labelledby="gridSystemModalLabel">
    </div>

    @if(!empty($can_user_import_export))
        <div class="modal fade" id="users_import_modal" tabindex="-1" role="dialog" aria-labelledby="usersImportModalLabel">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="usersImportModalLabel">Import Users</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>File</label>
                                    <input type="file" class="form-control" id="users_import_file" accept=".csv,.xlsx,.xls">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Import Mode</label>
                                    <select class="form-control" id="users_import_mode">
                                        <option value="insert" selected>Insert Only</option>
                                        <option value="update">Update Existing</option>
                                        <option value="upsert">Insert &amp; Update</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Default Password</label>
                                    <input type="text" class="form-control" id="users_default_password" value="12345678">
                                </div>
                            </div>
                        </div>

                        <div id="users_import_summary" class="well well-sm" style="display:none;"></div>

                        <div class="table-responsive" style="max-height: 320px; overflow:auto; display:none;" id="users_import_preview_wrap">
                            <table class="table table-bordered table-striped" id="users_import_preview_table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>First Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                        <th>Errors</th>
                                        <th>Warnings</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <input type="hidden" id="users_import_token" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                        <button type="button" class="btn btn-info" id="users_preview_btn"><i class="fa fa-eye"></i> Preview</button>
                        <button type="button" class="btn btn-primary" id="users_confirm_import_btn" disabled><i class="fa fa-check"></i> Confirm Import</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="users_export_modal" tabindex="-1" role="dialog" aria-labelledby="usersExportModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="usersExportModalLabel">Export Users</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Format</label>
                                    <select class="form-control" id="users_export_format">
                                        <option value="csv" selected>CSV</option>
                                        <option value="xlsx">Excel (XLSX)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="checkbox" style="margin-top: 32px;">
                                    <label>
                                        <input type="checkbox" id="users_export_include_inactive" value="1">
                                        Include inactive users
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" id="users_export_include_hashed_password" value="1">
                                        Include hashed passwords
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                        <button type="button" class="btn btn-primary" id="users_export_btn"><i class="fa fa-download"></i> Export</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</section>
<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">
    //Roles table
    $(document).ready( function(){
        var users_table = $('#users_table').DataTable({
                    processing: true,
                    serverSide: true,
                    fixedHeader:false,
                    ajax: '/users',
                    columnDefs: [ {
                        "targets": [4],
                        "orderable": false,
                        "searchable": false
                    } ],
                    "columns":[
                        {"data":"username"},
                        {"data":"full_name"},
                        {"data":"role"},
                        {"data":"email"},
                        {"data":"action"}
                    ]
                });
        $(document).on('click', 'button.delete_user_button', function(){
            swal({
              title: LANG.sure,
              text: LANG.confirm_delete_user,
              icon: "warning",
              buttons: true,
              dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    var href = $(this).data('href');
                    var data = $(this).serialize();
                    $.ajax({
                        method: "DELETE",
                        url: href,
                        dataType: "json",
                        data: data,
                        success: function(result){
                            if(result.success == true){
                                toastr.success(result.msg);
                                users_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
             });
        });

        @if(!empty($can_user_import_export))
            function resetUsersImportPreview() {
                $('#users_import_summary').hide().html('');
                $('#users_import_preview_wrap').hide();
                $('#users_import_preview_table tbody').empty();
                $('#users_import_token').val('');
                $('#users_confirm_import_btn').prop('disabled', true);
            }

            $('#users_import_modal').on('shown.bs.modal', function() {
                resetUsersImportPreview();
            });

            $('#users_import_modal').on('hidden.bs.modal', function() {
                $('#users_import_file').val('');
                resetUsersImportPreview();
            });

            $('#users_preview_btn').click(function() {
                resetUsersImportPreview();

                var fileInput = $('#users_import_file')[0];
                if (!fileInput.files || !fileInput.files.length) {
                    toastr.warning('Please choose a file first.');
                    return;
                }

                var formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('file', fileInput.files[0]);
                formData.append('mode', $('#users_import_mode').val());
                formData.append('default_password', $('#users_default_password').val());

                $.ajax({
                    method: 'POST',
                    url: '{{ action([\App\Http\Controllers\ManageUserController::class, 'previewImportUsers']) }}',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    beforeSend: function() {
                        $('#users_preview_btn').prop('disabled', true);
                        $('#users_confirm_import_btn').prop('disabled', true);
                    },
                    success: function(result) {
                        $('#users_preview_btn').prop('disabled', false);
                        if (!result || result.success !== true) {
                            toastr.error((result && result.msg) ? result.msg : LANG.something_went_wrong);
                            return;
                        }

                        $('#users_import_token').val(result.token || '');

                        var s = result.summary || {};
                        var summaryHtml = '<b>Total:</b> ' + (s.total_rows || 0)
                            + ' | <b>New:</b> ' + (s.new_rows || 0)
                            + ' | <b>Matched:</b> ' + (s.matched_rows || 0)
                            + ' | <b>Skipped:</b> ' + (s.skipped_rows || 0)
                            + ' | <b>Errors:</b> ' + (s.error_rows || 0)
                            + ' | <b>Warnings:</b> ' + (s.warning_rows || 0);
                        $('#users_import_summary').html(summaryHtml).show();

                        var rows = result.rows || [];
                        rows.forEach(function(r) {
                            var errors = (r.errors || []).join(', ');
                            var warnings = (r.warnings || []).join(', ');
                            var tr = '<tr>'
                                + '<td>' + (r.row_number || '') + '</td>'
                                + '<td>' + (r.first_name || '') + '</td>'
                                + '<td>' + (r.username || '') + '</td>'
                                + '<td>' + (r.email || '') + '</td>'
                                + '<td>' + (r.status || '') + '</td>'
                                + '<td>' + (r.action || '') + '</td>'
                                + '<td>' + errors + '</td>'
                                + '<td>' + warnings + '</td>'
                                + '</tr>';
                            $('#users_import_preview_table tbody').append(tr);
                        });
                        $('#users_import_preview_wrap').show();

                        $('#users_confirm_import_btn').prop('disabled', (s.error_rows || 0) > 0);
                    },
                    error: function() {
                        $('#users_preview_btn').prop('disabled', false);
                        toastr.error(LANG.something_went_wrong);
                    }
                });
            });

            $('#users_confirm_import_btn').click(function() {
                var token = $('#users_import_token').val();
                if (!token) {
                    toastr.warning('Please preview first.');
                    return;
                }

                $.ajax({
                    method: 'POST',
                    url: '{{ action([\App\Http\Controllers\ManageUserController::class, 'importUsers']) }}',
                    dataType: 'json',
                    data: {
                        _token: '{{ csrf_token() }}',
                        token: token,
                        mode: $('#users_import_mode').val(),
                        default_password: $('#users_default_password').val()
                    },
                    beforeSend: function() {
                        $('#users_confirm_import_btn').prop('disabled', true);
                    },
                    success: function(result) {
                        if (result && result.success) {
                            toastr.success(result.msg);
                            $('#users_import_modal').modal('hide');
                            users_table.ajax.reload();
                        } else {
                            toastr.error((result && result.msg) ? result.msg : LANG.something_went_wrong);
                            $('#users_confirm_import_btn').prop('disabled', false);
                        }
                    },
                    error: function() {
                        toastr.error(LANG.something_went_wrong);
                        $('#users_confirm_import_btn').prop('disabled', false);
                    }
                });
            });

            $('#users_export_btn').click(function() {
                var url = '{{ action([\App\Http\Controllers\ManageUserController::class, 'exportUsers']) }}'
                    + '?format=' + encodeURIComponent($('#users_export_format').val())
                    + '&include_inactive=' + ($('#users_export_include_inactive').is(':checked') ? 1 : 0)
                    + '&include_hashed_password=' + ($('#users_export_include_hashed_password').is(':checked') ? 1 : 0);
                window.location = url;
                $('#users_export_modal').modal('hide');
            });
        @endif
        
    });
    
    
</script>
@endsection
