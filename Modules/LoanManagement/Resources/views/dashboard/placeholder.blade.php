@extends('loanmanagement::layouts.app')
@section('title', 'Loan Management')
@section('content_body')
<section class="content-header">
    <h1>{{ $page }}</h1>
</section>
<section class="content">
    @if($page === 'Monthly Payments')
        <div class="lm-mobile-section-tabs">
            <a href="{{ route('loan-management.loans') }}">
                <i class="fa fa-credit-card"></i> Loans
            </a>
            <a href="{{ route('loan-management.monthly-payments.index') }}" class="active">
                <i class="fa fa-money"></i> Collection
            </a>
        </div>
    @endif

    <div class="box box-solid">
        <div class="box-body">
            <div class="row">
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-aqua"><i class="fa fa-database"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Source Table</span>
                            <span class="info-box-number">{{ $payload['summary']['table'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-green"><i class="fa fa-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Records</span>
                            <span class="info-box-number">{{ $payload['summary']['total'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if($page === 'Monthly Payments')
                <div class="lm-mobile-collection-list">
                    @forelse(($payload['rows'] ?? []) as $row)
                        <article class="lm-mobile-collection-card">
                            <div class="lm-mobile-collection-card-header">
                                <div>
                                    <div class="lm-mobile-collection-card-title">{{ $row['payment_ref_no'] ?? ('Payment #' . ($row['id'] ?? '-')) }}</div>
                                    <div class="lm-mobile-collection-card-date">{{ $row['paid_at'] ?? '-' }}</div>
                                </div>
                                <span class="lm-mobile-collection-status status-{{ \Illuminate\Support\Str::slug($row['status'] ?? 'unknown') }}">
                                    {{ $row['status'] ?? 'unknown' }}
                                </span>
                            </div>
                            <div class="lm-mobile-collection-main">
                                <small>Amount</small>
                                <strong>{{ $row['amount'] ?? '0.00' }}</strong>
                            </div>
                            <div class="lm-mobile-collection-grid">
                                <div><small>Loan ID</small><span>{{ $row['loan_id'] ?? '-' }}</span></div>
                                <div><small>Customer ID</small><span>{{ $row['customer_id'] ?? '-' }}</span></div>
                                <div><small>Channel</small><span>{{ $row['channel'] ?? '-' }}</span></div>
                                <div><small>Payment ID</small><span>{{ $row['id'] ?? '-' }}</span></div>
                            </div>
                        </article>
                    @empty
                        <div class="lm-mobile-loan-empty">No collection records found.</div>
                    @endforelse
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            @foreach(($payload['columns'] ?? []) as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($payload['rows'] ?? []) as $row)
                            <tr>
                                @foreach(($payload['columns'] ?? []) as $column)
                                    <td>{{ is_bool($row[$column] ?? null) ? (($row[$column] ?? false) ? 'true' : 'false') : ($row[$column] ?? '') }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(1, count($payload['columns'] ?? [])) }}" class="text-center text-muted">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
