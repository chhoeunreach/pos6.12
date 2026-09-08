@extends('loanmanagement::layouts.app')

@php
    $typeLabel = $typeLabelOverride ?? data_get($importTypes, $type.'.label', ucfirst(str_replace('_', ' ', $type)));
    $exportType = $exportType ?? $type;
    $exportFormat = $exportType === 'active_loans' ? 'xlsx' : 'csv';
@endphp

@section('title', $typeLabel.' Import Export')

@section('content_body')
<section class="content-header">
    <h1>{{ $typeLabel }} Import / Export</h1>
</section>

<section class="content">
    @if(session('status'))
        @php $status = session('status'); @endphp
        <div class="alert alert-{{ !empty($status['success']) ? 'success' : 'danger' }}">
            {{ $status['msg'] ?? '' }}
            @if(!empty($status['batch_id']) && (int) ($status['invalid_rows'] ?? 0) > 0)
                <div style="margin-top: 8px;">
                    <a class="btn btn-xs btn-warning" href="{{ route('loan-management.import.index', ['download_invalid' => $status['batch_id']]) }}">
                        <i class="fa fa-download"></i> Download invalid rows with line numbers
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger">
            <ul class="m-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Import {{ $typeLabel }}</h3>
                </div>
                <div class="box-body" style="border-bottom:1px solid #f4f4f4;">
                    <div class="btn-group">
                        <a class="btn btn-sm {{ $type === 'loans' ? 'btn-primary' : 'btn-default' }}" href="{{ route('loan-management.tools.loan-import-export') }}">
                            Full Installment Information
                        </a>
                        <a class="btn btn-sm {{ $type === 'full_loan_update' ? 'btn-primary' : 'btn-default' }}" href="{{ route('loan-management.import.index', ['type' => 'full_loan_update']) }}">
                            Full Update
                        </a>
                        <a class="btn btn-sm {{ $type === 'active_loans' ? 'btn-primary' : 'btn-default' }}" href="{{ route('loan-management.import.index', ['type' => 'active_loans']) }}">
                            Active / Ongoing Installments
                        </a>
                        <a class="btn btn-sm {{ $type === 'payments' ? 'btn-primary' : 'btn-default' }}" href="{{ route('loan-management.tools.monthly-import-export') }}">
                            Monthly Payments
                        </a>
                        <a class="btn btn-sm {{ $type === 'customer_deposit_payments' ? 'btn-primary' : 'btn-default' }}" href="{{ route('loan-management.import.index', ['type' => 'customer_deposit_payments']) }}">
                            Customer Deposit Payments
                        </a>
                    </div>
                    @if($type !== 'loans')
                        <a class="btn btn-sm btn-info pull-right" href="{{ route('loan-management.import.template', ['type' => 'loans']) }}">
                            <i class="fa fa-download"></i> Download Installment Template
                        </a>
                    @endif
                </div>
                <form method="POST" action="{{ route('loan-management.import.store') }}" enctype="multipart/form-data" id="loan_import_form">
                    @csrf
                    <div class="box-body">
                        <div class="alert alert-info">
                            <strong>{{ $typeLabel }}:</strong>
                            {{ data_get($importTypes, $type.'.description', '') }}
                        </div>
                        <input type="hidden" name="type" value="{{ $type }}">
                        <div class="well well-sm">
                            <strong>Required:</strong>
                            @foreach(($templateDetails['required'] ?? []) as $column)
                                <code>{{ $column }}</code>
                            @endforeach
                            <br>
                            <strong>Optional:</strong>
                            @foreach(($templateDetails['optional'] ?? []) as $column)
                                <code>{{ $column }}</code>
                            @endforeach
                            <p class="help-block" style="margin-bottom: 0;">{{ $templateDetails['notes'] ?? '' }}</p>
                        </div>
                        <div class="form-group">
                            <label>Template Columns</label>
                            <div class="well well-sm" style="max-height: 140px; overflow:auto;">
                                @foreach(($templateDetails['columns'] ?? []) as $column)
                                    <code style="display:inline-block; margin: 2px 4px 2px 0;">{{ $column }}</code>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Import File</label>
                            <input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx" required>
                            <p class="help-block">Use CSV or XLSX format. The template includes a header row and one example row.</p>
                        </div>
                        <div class="form-group">
                            <label>Duplicate Records</label>
                            <select name="duplicate_mode" class="form-control">
                                <option value="skip" {{ old('duplicate_mode', 'skip') === 'skip' ? 'selected' : '' }}>Skip duplicate</option>
                                <option value="replace" {{ old('duplicate_mode') === 'replace' ? 'selected' : '' }}>Replace existing</option>
                            </select>
                            <p class="help-block">Duplicate loans match by loan number. Duplicate payments match by <code>reference_number</code> when provided, otherwise by loan + schedule + payment type + paid date + amount + payment method. Duplicate schedules match by loan and installment number.</p>
                        </div>
                    </div>
                    <div class="box-body hide" id="loan_import_progress_wrap">
                        <div class="progress" style="margin-bottom: 8px;">
                            <div id="loan_import_progress_bar" class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%;">
                                0%
                            </div>
                        </div>
                        <p class="help-block" id="loan_import_progress_text" style="margin-bottom:0;">Preparing import...</p>
                        <div id="loan_import_retry_wrap" class="hide" style="margin-top: 10px;">
                            <button type="button" class="btn btn-warning btn-sm" id="loan_import_continue_btn">
                                <i class="fa fa-refresh"></i> Continue Import
                            </button>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary" id="loan_import_submit">
                            <i class="fa fa-upload"></i> Import
                        </button>
                        <a class="btn btn-default" href="{{ route('loan-management.import.template', ['type' => $type]) }}">
                            <i class="fa fa-download"></i> Download {{ $typeLabel }} Template
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Export {{ data_get($exportTypes, $exportType.'.label', $typeLabel) }}</h3>
                </div>
                <form method="GET" action="{{ route('loan-management.export.download') }}">
                    <input type="hidden" name="type" value="{{ $exportType }}">
                    <div class="box-body">
                        <div class="alert alert-info">
                            {{ data_get($exportTypes, $exportType.'.description', 'Export downloads data for this page.') }}
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <input type="text" name="status" class="form-control" value="{{ request('status') }}" placeholder="active, closed, confirmed, paid...">
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-download"></i> Export {{ strtoupper($exportFormat) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Imports</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th>File</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Imported</th>
                                <th>Invalid</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentBatches as $batch)
                                <tr>
                                    <td>{{ $batch->batch_code ?? $batch->id }}</td>
                                    <td>{{ $batch->file_name ?? '' }}</td>
                                    <td>{{ $batch->file_type ?? '' }}</td>
                                    <td>{{ $batch->status ?? '' }}</td>
                                    <td>{{ (int) ($batch->imported_rows ?? 0) }}</td>
                                     <td>{{ (int) ($batch->invalid_rows ?? 0) }}</td>
                                     <td>
                                         @if(in_array((string) ($batch->status ?? ''), ['pending', 'processing', 'failed'], true))
                                             <button type="button"
                                                 class="btn btn-xs btn-primary js-continue-import"
                                                 data-batch-id="{{ $batch->id }}"
                                                 data-duplicate-mode="skip">
                                                 <i class="fa fa-refresh"></i> Continue
                                             </button>
                                         @endif
                                         @if((int) ($batch->invalid_rows ?? 0) > 0)
                                             <a class="btn btn-xs btn-warning" href="{{ route('loan-management.import.index', ['download_invalid' => $batch->id]) }}">
                                                 <i class="fa fa-download"></i> Invalid rows
                                             </a>
                                         @elseif(!in_array((string) ($batch->status ?? ''), ['pending', 'processing', 'failed'], true))
                                             <span class="text-muted">-</span>
                                         @endif
                                     </td>
                                 </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">No imports yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Exports</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Rows</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentExports as $export)
                                <tr>
                                    <td>{{ $export->export_type ?? '' }}</td>
                                    <td>{{ $export->format ?? 'csv' }}</td>
                                    <td>{{ $export->status ?? '' }}</td>
                                    <td>{{ (int) ($export->rows_count ?? 0) }}</td>
                                    <td>{{ $export->created_at ?? '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No exports yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('loan_js')
    <script>
        (function($) {
            var processing = false;
            var currentBatchId = null;
            var currentDuplicateMode = 'skip';

            function setProgress(progress, message) {
                var percent = progress && typeof progress.percent !== 'undefined' ? parseInt(progress.percent, 10) : 0;
                percent = Math.max(0, Math.min(100, isNaN(percent) ? 0 : percent));
                $('#loan_import_progress_bar')
                    .css('width', percent + '%')
                    .attr('aria-valuenow', percent)
                    .text(percent + '%');

                var processed = progress ? (progress.processed_rows || 0) : 0;
                var total = progress ? (progress.total_rows || 0) : 0;
                var imported = progress ? (progress.imported_rows || 0) : 0;
                var invalid = progress ? (progress.invalid_rows || 0) : 0;
                var skipped = progress ? (progress.skipped_rows || 0) : 0;
                $('#loan_import_progress_text').text(message || ('Processing ' + processed + ' / ' + total + ' rows. Imported: ' + imported + ', skipped: ' + skipped + ', invalid: ' + invalid + '.'));
            }

            function finishProgress(progress) {
                $('#loan_import_retry_wrap').addClass('hide');
                setProgress(progress, 'Import completed. Refreshing results...');
                $('#loan_import_progress_bar').removeClass('active');
                window.setTimeout(function() {
                    window.location.reload();
                }, 900);
            }

            function showImportError(message) {
                processing = false;
                $('#loan_import_submit').prop('disabled', false);
                $('#loan_import_progress_bar').removeClass('active progress-bar-striped').addClass('progress-bar-danger');
                $('#loan_import_progress_text').text(message || 'Import failed.');
                if (currentBatchId) {
                    $('#loan_import_retry_wrap').removeClass('hide');
                }
            }

            function beginContinue(batchId, duplicateMode, progressMessage) {
                currentBatchId = batchId;
                currentDuplicateMode = duplicateMode || 'skip';
                processing = true;
                $('#loan_import_progress_wrap').removeClass('hide');
                $('#loan_import_retry_wrap').addClass('hide');
                $('#loan_import_progress_bar').removeClass('progress-bar-danger').addClass('progress-bar-striped active');
                $('#loan_import_submit').prop('disabled', true);

                $.ajax({
                    method: 'GET',
                    url: "{{ url('/loan-management/tools/import/progress') }}/" + batchId,
                    dataType: 'json',
                    success: function(response) {
                        if (!response.success) {
                            showImportError(response.msg || 'Unable to resume import.');
                            return;
                        }

                        setProgress(response.progress, progressMessage || 'Continuing saved import batch...');
                        processBatch(batchId, currentDuplicateMode);
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.msg ? xhr.responseJSON.msg : 'Unable to load saved import progress.';
                        showImportError(message);
                    }
                });
            }

            function processBatch(batchId, duplicateMode) {
                $.ajax({
                    method: 'POST',
                    url: "{{ route('loan-management.import.process') }}",
                    dataType: 'json',
                    data: {
                        _token: "{{ csrf_token() }}",
                        batch_id: batchId,
                        duplicate_mode: duplicateMode
                    },
                    success: function(response) {
                        if (!response.success) {
                            showImportError(response.msg);
                            return;
                        }

                        setProgress(response.progress);
                        if (response.progress.done || response.progress.percent >= 100) {
                            finishProgress(response.progress);
                            return;
                        }

                        window.setTimeout(function() {
                            processBatch(batchId, duplicateMode);
                        }, 250);
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.msg ? xhr.responseJSON.msg : 'Server stopped responding while importing.';
                        showImportError(message);
                    }
                });
            }

            $('#loan_import_form').on('submit', function(e) {
                if (processing) {
                    e.preventDefault();
                    return false;
                }

                e.preventDefault();
                processing = true;
                 currentBatchId = null;
                 currentDuplicateMode = $(this).find('[name="duplicate_mode"]').val() || 'skip';
                var form = this;
                var data = new FormData(form);

                $('#loan_import_progress_wrap').removeClass('hide');
                $('#loan_import_retry_wrap').addClass('hide');
                $('#loan_import_progress_bar').removeClass('progress-bar-danger').addClass('progress-bar-striped active');
                $('#loan_import_submit').prop('disabled', true);
                setProgress({percent: 0, processed_rows: 0, total_rows: 0, imported_rows: 0, invalid_rows: 0, skipped_rows: 0}, 'Uploading file and preparing rows...');

                $.ajax({
                    method: 'POST',
                    url: "{{ route('loan-management.import.start') }}",
                    data: data,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (!response.success) {
                            showImportError(response.msg);
                            return;
                        }

                        currentBatchId = response.progress.batch_id;
                        setProgress(response.progress, 'Upload complete. Starting import...');
                        processBatch(response.progress.batch_id, currentDuplicateMode);
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.msg ? xhr.responseJSON.msg : 'Unable to start import.';
                        showImportError(message);
                    }
                });

                return false;
            });

            $('#loan_import_continue_btn').on('click', function() {
                if (!currentBatchId || processing) {
                    return;
                }

                beginContinue(currentBatchId, currentDuplicateMode, 'Retrying saved import batch...');
            });

            $('.js-continue-import').on('click', function() {
                if (processing) {
                    return;
                }

                var batchId = $(this).data('batch-id');
                var duplicateMode = $(this).data('duplicate-mode') || 'skip';
                beginContinue(batchId, duplicateMode, 'Loading saved import batch...');
            });
        })(jQuery);
    </script>
@endsection
