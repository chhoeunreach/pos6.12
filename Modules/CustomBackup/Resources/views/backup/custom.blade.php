@extends('layouts.app')
@section('title', 'Custom Backup & Import')

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Custom Backup &amp; Import
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Export selected data or import a custom backup file</small>
    </h1>
</section>

<section class="content">
    @php
        $active_tab = $active_tab ?? (request()->segment(2) == 'import' ? 'import' : 'export');
    @endphp

    @if (session('status'))
        @php $status = session('status'); @endphp
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-{{ !empty($status['success']) && $status['success'] ? 'success' : 'danger' }} alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    {{ $status['msg'] ?? '' }}
                </div>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            <li class="{{ $active_tab == 'export' ? 'active' : '' }}">
                <a href="{{ action([\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'index']) }}">
                    <i class="fa fa-download"></i> Custom Backup
                </a>
            </li>
            <li class="{{ $active_tab == 'import' ? 'active' : '' }}">
                <a href="{{ action([\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'showImportForm']) }}">
                    <i class="fa fa-upload"></i> Import Backup
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane {{ $active_tab == 'export' ? 'active' : '' }}" id="custom_backup_export_tab">
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary'])
                {!! Form::open(['url' => action([\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'export']), 'method' => 'post']) !!}
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            {!! Form::label('custom_backup_date_range', 'Date Range:') !!}
                            <input type="text" id="custom_backup_date_range" class="form-control" readonly placeholder="{{ __('report.select_a_date_range') }}">
                            {!! Form::hidden('from_date', old('from_date'), ['id' => 'from_date']) !!}
                            {!! Form::hidden('to_date', old('to_date'), ['id' => 'to_date']) !!}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group" style="margin-top: 25px;">
                            <label>
                                <input type="checkbox" id="full_backup" name="full_backup" value="1" {{ old('full_backup') ? 'checked' : '' }}>
                                Full Backup (entire database)
                            </label>
                            <div class="text-muted">
                                If checked, system generates a full database backup zip (ignores Date Range and module selection).
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <label>Export Modules:</label>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[products]', 1, old('modules.products')) !!} Products
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[repair]', 1, old('modules.repair')) !!} Repair
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[purchases]', 1, old('modules.purchases')) !!} Purchases
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[sell]', 1, old('modules.sell')) !!} Sell
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[stock_transfers]', 1, old('modules.stock_transfers')) !!} Stock Transfers
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[stock_adjustment]', 1, old('modules.stock_adjustment')) !!} Stock Adjustment
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[expenses]', 1, old('modules.expenses')) !!} Expenses
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[users_permissions]', 1, old('modules.users_permissions')) !!} Users &amp; Permissions
                                    </label>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">At least one checkbox is required.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                            <i class="fa fa-download"></i> Export SQL
                        </button>
                    </div>
                </div>

                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
            </div>

            <div class="tab-pane {{ $active_tab == 'import' ? 'active' : '' }}" id="custom_backup_import_tab">
                <div class="row">
                    <div class="col-sm-12">
                        @component('components.widget', ['class' => 'box-primary'])
                            {!! Form::open(['url' => action([\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'import']), 'method' => 'post', 'enctype' => 'multipart/form-data']) !!}

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('backup_sql', 'SQL file (.sql/.txt):') !!}
                                        {!! Form::file('backup_sql', ['accept'=> '.sql,.txt', 'required' => 'required']) !!}
                                        <p class="help-block">Max size: {{ config('constants.custom_backup_import_max_kb') }} KB</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <label>Conflict handling:</label>
                                    <div class="radio">
                                        <label>
                                            {!! Form::radio('conflict_mode', 'insert', old('conflict_mode', 'insert') == 'insert') !!}
                                            Insert only
                                        </label>
                                    </div>
                                    <div class="radio">
                                        <label>
                                            {!! Form::radio('conflict_mode', 'ignore', old('conflict_mode') == 'ignore') !!}
                                            Insert ignore
                                        </label>
                                    </div>
                                    <div class="radio">
                                        <label>
                                            {!! Form::radio('conflict_mode', 'replace', old('conflict_mode') == 'replace') !!}
                                            Replace existing
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <label>Users &amp; Permissions conflicts (by email):</label>
                                    <div class="radio">
                                        <label>
                                            {!! Form::radio('user_conflict', 'skip', old('user_conflict', 'skip') == 'skip') !!}
                                            Skip existing users
                                        </label>
                                    </div>
                                    <div class="radio">
                                        <label>
                                            {!! Form::radio('user_conflict', 'update', old('user_conflict') == 'update') !!}
                                            Update existing users (except password)
                                        </label>
                                    </div>
                                    <div class="radio">
                                        <label>
                                            {!! Form::radio('user_conflict', 'replace', old('user_conflict') == 'replace') !!}
                                            Replace existing users (including hashed password)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="checkbox">
                                        <label>
                                            {!! Form::checkbox('confirm_risk', 1, old('confirm_risk')) !!}
                                            I understand this may overwrite/duplicate data
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12">
                                    <button type="submit" id="custom_backup_import_btn" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                                        <i class="fa fa-upload"></i> Import SQL
                                    </button>
                                    <span id="custom_backup_import_status" class="text-muted" style="display:none; margin-left:10px;">
                                        <i class="fa fa-spinner fa-spin"></i> Creating safety backup and importing...
                                    </span>
                                </div>
                            </div>

                            {!! Form::close() !!}
                        @endcomponent
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        // Date Range (like sales filters)
        var oldFrom = $('#from_date').val();
        var oldTo = $('#to_date').val();

        var start = moment().subtract(29, 'days');
        var end = moment();

        if (oldFrom && oldTo) {
            var parsedStart = moment(oldFrom, moment_date_format);
            var parsedEnd = moment(oldTo, moment_date_format);
            if (parsedStart.isValid() && parsedEnd.isValid()) {
                start = parsedStart;
                end = parsedEnd;
                $('#custom_backup_date_range').val(
                    start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                );
            }
        }

        $('#custom_backup_date_range').daterangepicker(
            $.extend(true, {}, dateRangeSettings, { startDate: start, endDate: end }),
            function(start, end) {
                $('#custom_backup_date_range').val(
                    start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                );
                $('#from_date').val(start.format(moment_date_format));
                $('#to_date').val(end.format(moment_date_format));
            }
        );

        $('#custom_backup_date_range').on('apply.daterangepicker', function(ev, picker) {
            $('#custom_backup_date_range').val(
                picker.startDate.format(moment_date_format) + ' ~ ' + picker.endDate.format(moment_date_format)
            );
            $('#from_date').val(picker.startDate.format(moment_date_format));
            $('#to_date').val(picker.endDate.format(moment_date_format));
        });

        $('#custom_backup_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#custom_backup_date_range').val('');
            $('#from_date').val('');
            $('#to_date').val('');
        });

        function toggleFullBackupUI() {
            var isFull = $('#full_backup').is(':checked');

            $('#custom_backup_date_range').prop('disabled', isFull);
            if (isFull) {
                $('#custom_backup_date_range').val('');
                $('#from_date').val('');
                $('#to_date').val('');
            }

            // Disable module selection when full backup is chosen
            $('input[name^="modules["]').prop('disabled', isFull);
        }

        $('#full_backup').on('change', toggleFullBackupUI);
        toggleFullBackupUI();

        $('form[action$="/custom-backup/import"]').on('submit', function () {
            $('#custom_backup_import_btn').prop('disabled', true);
            $('#custom_backup_import_status').show();
        });
    });
</script>
@endsection
