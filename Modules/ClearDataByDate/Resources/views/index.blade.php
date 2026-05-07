@extends('layouts.app')

@section('title', __('lang_v1.settings') . ' - Clear Data by Date')

@section('content')
<section class="content-header">
    <h1>Clear Data by Date</h1>
</section>

<section class="content">
    @if (session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif

    <div class="alert alert-warning">
        <strong>Warning:</strong> This will permanently delete data. Always take a full database backup before deleting.
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">1) Select date range & modules</h3>
        </div>
        <div class="box-body">
            <form method="POST" action="{{ route('clear_data_by_date.preview') }}">
                @csrf

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Date Range *</label>
                            <input type="text" id="clear_data_by_date_date_range" class="form-control" readonly required placeholder="{{ __('report.select_a_date_range') }}">
                            <input type="hidden" id="clear_data_by_date_start_date" name="start_date" value="{{ old('start_date', $start_date_input ?? '') }}">
                            <input type="hidden" id="clear_data_by_date_end_date" name="end_date" value="{{ old('end_date', $end_date_input ?? '') }}">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Business Location (optional)</label>
                            <select name="location_id" class="form-control">
                                <option value="">All locations</option>
                                @foreach (($business_locations ?? []) as $id => $name)
                                    <option value="{{ $id }}" @selected((string) old('location_id', $location_id ?? '') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">Preview</button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <label>Modules to clear *</label>
                        <div class="row">
                            @php
                                $selected = (array) old('modules', $modules ?? []);
                            @endphp
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="sales" @checked(in_array('sales', $selected, true))>
                                        Sales / Sell
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="purchases" @checked(in_array('purchases', $selected, true))>
                                        Purchases
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="stock_transfers" @checked(in_array('stock_transfers', $selected, true))>
                                        Stock Transfers
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="stock_adjustments" @checked(in_array('stock_adjustments', $selected, true))>
                                        Stock Adjustments
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="expenses" @checked(in_array('expenses', $selected, true))>
                                        Expenses
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="repair" @checked(in_array('repair', $selected, true))>
                                        Repair data (if module exists)
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="payments_only" @checked(in_array('payments_only', $selected, true))>
                                        Payments only (in date range)
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="modules[]" value="activity_logs" @checked(in_array('activity_logs', $selected, true))>
                                        Activity logs (optional)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Master data (users/products/categories/brands/units/taxes/settings) are never deleted.</small>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">2) Preview result</h3>
        </div>
        <div class="box-body">
            @if (empty($preview_counts))
                <p class="text-muted">Click <strong>Preview</strong> to see counts before deleting.</p>
            @else
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Counts</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview_counts as $module => $counts)
                            <tr>
                                <td>{{ $module }}</td>
                                <td><pre style="margin:0;border:0;background:transparent;">{{ json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <hr>

                <form method="POST" action="{{ route('clear_data_by_date.delete') }}">
                    @csrf
                    <input type="hidden" name="preview_token" value="{{ $token }}">

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Type confirmation text *</label>
                                <input type="text" name="confirm_text" class="form-control" placeholder="Type DELETE" required>
                                <small class="text-muted">Type exactly: <code>DELETE</code></small>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Your password *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-danger btn-block">Delete Now</button>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="dry_run" value="1">
                                    Dry-run mode (do not delete, only log)
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-6 text-right">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="continue_on_blocked" value="1" checked>
                                    Continue if some records are blocked
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        if (!$('#clear_data_by_date_date_range').length) {
            return;
        }

        var oldStart = $('#clear_data_by_date_start_date').val();
        var oldEnd = $('#clear_data_by_date_end_date').val();

        var start = moment().subtract(29, 'days');
        var end = moment();

        // If server provided previous values, prefer them
        if (oldStart && oldEnd) {
            var parsedStart = moment(oldStart, moment_date_format);
            var parsedEnd = moment(oldEnd, moment_date_format);
            if (parsedStart.isValid() && parsedEnd.isValid()) {
                start = parsedStart;
                end = parsedEnd;
                $('#clear_data_by_date_date_range').val(
                    start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                );
            }
        }

        $('#clear_data_by_date_date_range').daterangepicker(
            $.extend(true, {}, dateRangeSettings, { startDate: start, endDate: end }),
            function(start, end) {
                $('#clear_data_by_date_date_range').val(
                    start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                );
                $('#clear_data_by_date_start_date').val(start.format(moment_date_format));
                $('#clear_data_by_date_end_date').val(end.format(moment_date_format));
            }
        );

        $('#clear_data_by_date_date_range').on('apply.daterangepicker', function(ev, picker) {
            $('#clear_data_by_date_date_range').val(
                picker.startDate.format(moment_date_format) + ' ~ ' + picker.endDate.format(moment_date_format)
            );
            $('#clear_data_by_date_start_date').val(picker.startDate.format(moment_date_format));
            $('#clear_data_by_date_end_date').val(picker.endDate.format(moment_date_format));
        });

        $('#clear_data_by_date_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#clear_data_by_date_date_range').val('');
            $('#clear_data_by_date_start_date').val('');
            $('#clear_data_by_date_end_date').val('');
        });
    });
</script>
@endsection
