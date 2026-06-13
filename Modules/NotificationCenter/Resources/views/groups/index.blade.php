@extends('layouts.app')
@section('title', 'Notification Groups')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-7xl">
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3 tw-flex-wrap tw-gap-2">
        <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800">Telegram Groups</h1>
        <div class="tw-flex tw-items-center tw-gap-2">
            <a href="{{ route('notificationcenter.groups.download-template') }}"
               class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary">
                <i class="fa fa-download"></i> Template
            </a>
            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary"
                    data-toggle="modal" data-target="#ng_import_modal">
                <i class="fa fa-upload"></i> Import
            </button>
            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary"
                    data-toggle="modal" data-target="#ng_export_modal">
                <i class="fa fa-file-excel-o"></i> Export
            </button>
            <a href="{{ route('notificationcenter.groups.create') }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary">+ New Group</a>
        </div>
    </div>

    {{-- From Channels --}}
    <div class="tw-mb-6">
        <h2 class="tw-text-base tw-font-semibold tw-text-gray-700 tw-mb-2">From Channels</h2>
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border">
            <table class="tw-w-full tw-text-sm">
                <thead class="tw-bg-gray-50 tw-border-b">
                    <tr>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Location</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Chat ID</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Text</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">PDF</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Active</th>
                        <th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="tw-divide-y">
                    @forelse($fromGroups as $group)
                    <tr class="hover:tw-bg-gray-50">
                        <td class="tw-px-4 tw-py-3">{{ $group->location_name ?: $group->name }}</td>
                        <td class="tw-px-4 tw-py-3"><code class="tw-text-xs tw-bg-gray-100 tw-px-1 tw-py-0.5 tw-rounded">{{ $group->chat_id }}</code></td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_text)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_pdf)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->active)<span class="tw-text-green-600">Active</span>@else<span class="tw-text-red-500">Inactive</span>@endif</td>
                        <td class="tw-px-4 tw-py-3 tw-text-right tw-space-x-1">
                            <a href="{{ route('notificationcenter.groups.edit', $group->id) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info">Edit</a>
                            <form action="{{ route('notificationcenter.groups.test', $group->id) }}" method="POST" class="tw-inline">
                                @csrf
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success">Test</button>
                            </form>
                            <form action="{{ route('notificationcenter.groups.destroy', $group->id) }}" method="POST" class="tw-inline" onsubmit="return confirm('Delete this group?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="tw-px-4 tw-py-8 tw-text-center tw-text-gray-400">No from-channel groups configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- To Channels --}}
    <div class="tw-mb-6">
        <h2 class="tw-text-base tw-font-semibold tw-text-gray-700 tw-mb-2">To Channels</h2>
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border">
            <table class="tw-w-full tw-text-sm">
                <thead class="tw-bg-gray-50 tw-border-b">
                    <tr>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Location</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Chat ID</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Text</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">PDF</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Active</th>
                        <th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="tw-divide-y">
                    @forelse($toGroups as $group)
                    <tr class="hover:tw-bg-gray-50">
                        <td class="tw-px-4 tw-py-3">{{ $group->location_name ?: $group->name }}</td>
                        <td class="tw-px-4 tw-py-3"><code class="tw-text-xs tw-bg-gray-100 tw-px-1 tw-py-0.5 tw-rounded">{{ $group->chat_id }}</code></td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_text)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_pdf)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->active)<span class="tw-text-green-600">Active</span>@else<span class="tw-text-red-500">Inactive</span>@endif</td>
                        <td class="tw-px-4 tw-py-3 tw-text-right tw-space-x-1">
                            <a href="{{ route('notificationcenter.groups.edit', $group->id) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info">Edit</a>
                            <form action="{{ route('notificationcenter.groups.test', $group->id) }}" method="POST" class="tw-inline">
                                @csrf
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success">Test</button>
                            </form>
                            <form action="{{ route('notificationcenter.groups.destroy', $group->id) }}" method="POST" class="tw-inline" onsubmit="return confirm('Delete this group?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="tw-px-4 tw-py-8 tw-text-center tw-text-gray-400">No to-channel groups configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="ng_import_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Import Notification Groups</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>File (CSV/XLSX):</label>
                    <input type="file" class="form-control" id="ng_import_file" accept=".csv,.xlsx,.xls">
                    <p class="help-block">Download template → fill → upload.</p>
                </div>
                <div class="form-group">
                    <label>Import Mode:</label>
                    <select class="form-control" id="ng_import_mode">
                        <option value="insert">Insert Only</option>
                        <option value="update">Update Existing</option>
                        <option value="upsert" selected>Insert & Update</option>
                    </select>
                </div>
                <hr>
                <div id="ng_preview_summary" class="well well-sm" style="display:none;"></div>
                <div class="table-responsive" style="max-height:320px; overflow:auto; display:none;" id="ng_preview_table_wrap">
                    <table class="table table-bordered table-striped" id="ng_preview_table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Chat ID</th>
                                <th>Module</th>
                                <th>Status</th>
                                <th>Action</th>
                                <th>Errors</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <input type="hidden" id="ng_import_token" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" id="ng_preview_btn"><i class="fa fa-eye"></i> Preview</button>
                <button type="button" class="btn btn-primary" id="ng_confirm_import_btn" disabled><i class="fa fa-check"></i> Confirm Import</button>
            </div>
        </div>
    </div>
</div>

{{-- Export Modal --}}
<div class="modal fade" id="ng_export_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Export Notification Groups</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Format:</label>
                            <select class="form-control" id="ng_export_format">
                                <option value="csv" selected>CSV</option>
                                <option value="xlsx">Excel (XLSX)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group" style="margin-top:25px;">
                            <label><input type="checkbox" id="ng_export_include_inactive" value="1"> Include inactive</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="ng_export_btn"><i class="fa fa-download"></i> Export</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        function resetImportPreview() {
            $('#ng_preview_summary').hide().html('');
            $('#ng_preview_table_wrap').hide();
            $('#ng_preview_table tbody').empty();
            $('#ng_import_token').val('');
            $('#ng_import_token').data('mode', '');
            $('#ng_confirm_import_btn').prop('disabled', true);
        }

        $('#ng_import_modal').on('shown.bs.modal', function() { resetImportPreview(); });
        $('#ng_import_modal').on('hidden.bs.modal', function() { $('#ng_import_file').val(''); resetImportPreview(); });
        $('#ng_import_file, #ng_import_mode').on('change', function() { resetImportPreview(); });

        $('#ng_preview_btn').click(function() {
            resetImportPreview();
            var fileInput = $('#ng_import_file')[0];
            if (!fileInput.files || !fileInput.files.length) {
                toastr.warning('Please choose a file first.');
                return;
            }
            var formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('file', fileInput.files[0]);
            var previewMode = $('#ng_import_mode').val();
            formData.append('mode', previewMode);

            $.ajax({
                method: 'POST',
                url: '{{ route("notificationcenter.groups.import-preview") }}',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() { $('#ng_preview_btn').prop('disabled', true); $('#ng_confirm_import_btn').prop('disabled', true); },
                success: function(result) {
                    $('#ng_preview_btn').prop('disabled', false);
                    if (!result || result.success !== true) {
                        toastr.error((result && result.msg) ? result.msg : 'Something went wrong');
                        return;
                    }
                    $('#ng_import_token').val(result.token || '').data('mode', previewMode);
                    var s = result.summary || {};
                    var summaryHtml = '<b>Total:</b> ' + (s.total_rows || 0)
                        + ' | <b>Mode:</b> ' + $('#ng_import_mode option:selected').text()
                        + ' | <b>New:</b> ' + (s.new_rows || 0)
                        + ' | <b>Existing:</b> ' + (s.existing_rows || 0)
                        + ' | <b>Skipped:</b> ' + (s.skipped_rows || 0)
                        + ' | <b>Errors:</b> ' + (s.error_rows || 0);
                    $('#ng_preview_summary').html(summaryHtml).show();

                    (result.rows || []).forEach(function(r) {
                        var errors = (r.errors || []).join(', ');
                        var tr = '<tr>'
                            + '<td>' + (r.row_number || '') + '</td>'
                            + '<td>' + (r.name || '') + '</td>'
                            + '<td>' + (r.chat_id || '') + '</td>'
                            + '<td>' + (r.module_type || '') + '</td>'
                            + '<td>' + (r.status || '') + '</td>'
                            + '<td>' + (r.action || '') + '</td>'
                            + '<td>' + errors + '</td></tr>';
                        $('#ng_preview_table tbody').append(tr);
                    });
                    $('#ng_preview_table_wrap').show();

                    if ((s.error_rows || 0) > 0) {
                        toastr.warning('Fix errors first, then preview again.');
                        $('#ng_confirm_import_btn').prop('disabled', true);
                    } else {
                        $('#ng_confirm_import_btn').prop('disabled', false);
                    }
                },
                error: function() { $('#ng_preview_btn').prop('disabled', false); toastr.error('Something went wrong'); }
            });
        });

        $('#ng_confirm_import_btn').click(function() {
            var token = $('#ng_import_token').val();
            if (!token) { toastr.warning('Please preview first.'); return; }
            $.ajax({
                method: 'POST',
                url: '{{ route("notificationcenter.groups.import-confirm") }}',
                dataType: 'json',
                data: {
                    _token: '{{ csrf_token() }}',
                    token: token,
                    mode: $('#ng_import_token').data('mode') || $('#ng_import_mode').val()
                },
                beforeSend: function() { $('#ng_confirm_import_btn').prop('disabled', true); },
                success: function(result) {
                    if (result && result.success) {
                        toastr.success(result.msg);
                        $('#ng_import_modal').modal('hide');
                        location.reload();
                    } else {
                        toastr.error((result && result.msg) ? result.msg : 'Something went wrong');
                        $('#ng_confirm_import_btn').prop('disabled', false);
                    }
                },
                error: function() { toastr.error('Something went wrong'); $('#ng_confirm_import_btn').prop('disabled', false); }
            });
        });

        $('#ng_export_btn').click(function() {
            var format = $('#ng_export_format').val();
            var include_inactive = $('#ng_export_include_inactive').is(':checked') ? 1 : 0;
            var url = '{{ route("notificationcenter.groups.export") }}'
                + '?format=' + encodeURIComponent(format)
                + '&include_inactive=' + encodeURIComponent(include_inactive);
            window.location = url;
            $('#ng_export_modal').modal('hide');
        });
    });
</script>
@endsection
