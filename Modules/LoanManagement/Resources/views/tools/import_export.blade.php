@extends('loanmanagement::layouts.app')

@php
    $typeLabel = data_get($importTypes, $type.'.label', ucfirst(str_replace('_', ' ', $type)));
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
                <form method="POST" action="{{ route('loan-management.import.store') }}" enctype="multipart/form-data">
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
                            <p class="help-block">Duplicate loans match by loan number. Duplicate payments match by reference number when provided. Duplicate schedules match by loan and installment number.</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-upload"></i> Import
                        </button>
                        <a class="btn btn-default" href="{{ route('loan-management.import.template', ['type' => $type]) }}">
                            <i class="fa fa-download"></i> Download Template
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Export {{ data_get($exportTypes, $type.'.label', $typeLabel) }}</h3>
                </div>
                <form method="GET" action="{{ route('loan-management.export.download') }}">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <div class="box-body">
                        <div class="alert alert-info">
                            {{ data_get($exportTypes, $type.'.description', 'Export downloads CSV data for this page.') }}
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
                            <i class="fa fa-download"></i> Export CSV
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
                                        @if((int) ($batch->invalid_rows ?? 0) > 0)
                                            <a class="btn btn-xs btn-warning" href="{{ route('loan-management.import.index', ['download_invalid' => $batch->id]) }}">
                                                <i class="fa fa-download"></i> Invalid rows
                                            </a>
                                        @else
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
