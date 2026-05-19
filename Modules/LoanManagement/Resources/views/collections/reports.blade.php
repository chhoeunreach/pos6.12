@extends('loanmanagement::layouts.app')
@section('title', 'Collection Reports')
@section('content_body')
<section class="content-header">
    <h1>Collection Reports</h1>
</section>
<section class="content">
    <div class="row">
        @foreach([
            'due_today' => 'Due Today',
            'overdue_accounts' => 'Overdue Accounts',
            'skip_customers' => 'Skip Customers',
            'broken_ptp' => 'Broken PTP',
            'field_visits_today' => 'Field Visits Today',
            'collection_amount_today' => 'Collection Amount Today',
            'recovery_cases' => 'Recovery Cases',
            'legal_cases' => 'Legal Cases',
            'high_risk_customers' => 'High Risk Customers',
            'repossessions' => 'Repossessions',
        ] as $key => $label)
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-blue"><i class="fa fa-line-chart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ $key === 'collection_amount_today' ? number_format((float)($cards[$key] ?? 0), 2) : (int)($cards[$key] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Reports</h3></div>
        <div class="box-body">
            <div class="row">
                @foreach($options['reports'] ?? [] as $key => $label)
                    <div class="col-md-4">
                        <a class="btn btn-default btn-block" style="margin-bottom:10px;" href="{{ route('loan-management.collection.report', $key) }}">
                            <i class="fa fa-file-text-o"></i> {{ $label }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endsection
