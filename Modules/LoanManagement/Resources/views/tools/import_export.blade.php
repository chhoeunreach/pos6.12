@extends('loanmanagement::layouts.app')

@section('title', 'Loan Import Export')

@section('content_body')
<section class="content-header">
    <h1>Import / Export</h1>
</section>

<section class="content">
    @if(session('status'))
        @php $status = session('status'); @endphp
        <div class="alert alert-{{ !empty($status['success']) ? 'success' : 'danger' }}">
            {{ $status['msg'] ?? '' }}
        </div>
    @endif

    @if($errors->any())
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
                    <h3 class="box-title">Import {{ $type === 'payments' ? 'Payments' : 'Loans' }}</h3>
                </div>
                <form method="POST" action="{{ route('loan-management.import.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="box-body">
                        <div class="alert alert-info">
                            @if($type === 'payments')
                                <strong>Monthly Payment Import:</strong> use this for installment collections. Provide either <code>loan_number</code> or <code>loan_id</code>. If <code>schedule_id</code> is empty, payment goes to the oldest unpaid schedule.
                            @else
                                <strong>Loan Import:</strong> use this to create customer loan records, product snapshot, and monthly schedules. If <code>customer_id</code> is empty, the importer can create/find a customer by phone.
                            @endif
                        </div>
                        <div class="form-group">
                            <label>Import Type</label>
                            <select name="type" class="form-control">
                                <option value="loans" {{ $type === 'loans' ? 'selected' : '' }}>Loans</option>
                                <option value="payments" {{ $type === 'payments' ? 'selected' : '' }}>Payments</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>CSV File</label>
                            <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                            <p class="help-block">Use CSV format. The template includes a header row and one example row.</p>
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
                    <h3 class="box-title">Export {{ $type === 'payments' ? 'Payments' : 'Loans' }}</h3>
                </div>
                <form method="GET" action="{{ route('loan-management.export.download') }}">
                    <div class="box-body">
                        <div class="alert alert-info">
                            Export downloads the current loan or monthly payment data as CSV. Use date filters to export a month, week, or custom range.
                        </div>
                        <div class="form-group">
                            <label>Export Type</label>
                            <select name="type" class="form-control">
                                <option value="loans" {{ $type === 'loans' ? 'selected' : '' }}>Loans</option>
                                <option value="payments" {{ $type === 'payments' ? 'selected' : '' }}>Payments</option>
                            </select>
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
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No imports yet.</td></tr>
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
