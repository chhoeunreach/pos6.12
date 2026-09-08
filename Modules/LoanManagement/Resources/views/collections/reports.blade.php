@extends('loanmanagement::layouts.app')
@section('title', 'Collection Reports')

@php
    $summaryCards = [
        'due_today' => ['label' => 'Due Today', 'icon' => 'fa fa-calendar-check-o', 'tone' => 'blue', 'hint' => 'Accounts scheduled for payment today'],
        'overdue_accounts' => ['label' => 'Overdue Accounts', 'icon' => 'fa fa-exclamation-triangle', 'tone' => 'red', 'hint' => 'Accounts past the expected payment date'],
        'skip_customers' => ['label' => 'Skip Customers', 'icon' => 'fa fa-user-times', 'tone' => 'slate', 'hint' => 'Customers requiring tracing or contact review'],
        'broken_ptp' => ['label' => 'Broken PTP', 'icon' => 'fa fa-chain-broken', 'tone' => 'orange', 'hint' => 'Promises to pay that were missed'],
        'field_visits_today' => ['label' => 'Field Visits Today', 'icon' => 'fa fa-map-marker', 'tone' => 'violet', 'hint' => 'Visits due for collector follow-up'],
        'collection_amount_today' => ['label' => 'Collected Today', 'icon' => 'fa fa-money', 'tone' => 'green', 'hint' => 'Total collection amount recorded today', 'money' => true],
        'recovery_cases' => ['label' => 'Recovery Cases', 'icon' => 'fa fa-refresh', 'tone' => 'cyan', 'hint' => 'Installments currently in recovery workflow'],
        'legal_cases' => ['label' => 'Legal Cases', 'icon' => 'fa fa-gavel', 'tone' => 'red', 'hint' => 'Accounts escalated to legal handling'],
        'high_risk_customers' => ['label' => 'High Risk', 'icon' => 'fa fa-warning', 'tone' => 'orange', 'hint' => 'Customers marked high risk or critical'],
        'repossessions' => ['label' => 'Repossessions', 'icon' => 'fa fa-archive', 'tone' => 'slate', 'hint' => 'Assets or accounts under repossession'],
    ];

    $reportMeta = [
        'overdue-aging' => ['icon' => 'fa fa-clock-o', 'description' => 'Review overdue exposure by aging bucket and urgency.'],
        'skip-customers' => ['icon' => 'fa fa-user-times', 'description' => 'Track customers that require skip tracing or contact recovery.'],
        'collector-performance' => ['icon' => 'fa fa-line-chart', 'description' => 'Compare collector output, recovery activity, and follow-up results.'],
        'recovery' => ['icon' => 'fa fa-refresh', 'description' => 'Monitor recovery cases and collection movement.'],
        'ptp-compliance' => ['icon' => 'fa fa-handshake-o', 'description' => 'Audit promise-to-pay commitments and fulfillment.'],
        'broken-promise' => ['icon' => 'fa fa-chain-broken', 'description' => 'Find missed promises and accounts needing action.'],
        'legal-cases' => ['icon' => 'fa fa-gavel', 'description' => 'Review accounts escalated for legal handling.'],
        'repossession' => ['icon' => 'fa fa-archive', 'description' => 'Track repossession workflow and account status.'],
        'risk-analysis' => ['icon' => 'fa fa-shield', 'description' => 'Analyze customer risk level and portfolio quality.'],
    ];
@endphp

@section('content_body')
<section class="content-header">
    <h1>Collection Reports</h1>
</section>
<section class="content">
    <div class="lm-collection-report-hero">
        <div>
            <span class="lm-collection-report-eyebrow">Portfolio overview</span>
            <h2>Collection performance center</h2>
            <p>Quickly review collection health, risk movement, and recovery reports from one focused workspace.</p>
        </div>
        <a href="{{ route('loan-management.collection.report', 'overdue-aging') }}" class="btn btn-primary">
            <i class="fa fa-file-text-o"></i> Open Aging Report
        </a>
    </div>

    @php
        $targetRoutes = [
            'due_today' => route('loan-management.operations.page', ['page' => 'due-today']),
            'overdue_accounts' => route('loan-management.collection.page', ['page' => 'overdue-accounts']),
            'skip_customers' => route('loan-management.collection.page', ['page' => 'skip-customers']),
            'broken_ptp' => route('loan-management.collection.page', ['page' => 'broken-promise']),
            'field_visits_today' => route('loan-management.collection-visits.index', ['date_from' => \Carbon\Carbon::today()->toDateString(), 'date_to' => \Carbon\Carbon::today()->toDateString()]),
            'collection_amount_today' => route('loan-management.payments.index'),
            'recovery_cases' => route('loan-management.collection.page', ['page' => 'recovery-management']),
            'legal_cases' => route('loan-management.collection.page', ['page' => 'legal-cases']),
            'high_risk_customers' => route('loan-management.collection.page', ['page' => 'high-risk-customers']),
            'repossessions' => route('loan-management.collection.page', ['page' => 'repossessions']),
        ];
    @endphp

    <div class="lm-collection-summary-grid">
        @foreach($summaryCards as $key => $card)
            <a href="{{ $targetRoutes[$key] ?? '#' }}" class="lm-collection-summary-card tone-{{ $card['tone'] }}" style="text-decoration: none; cursor: pointer;">
                <div class="lm-collection-summary-icon"><i class="{{ $card['icon'] }}"></i></div>
                <div class="lm-collection-summary-copy">
                    <span>{{ $card['label'] }}</span>
                    <strong>
                        @if(!empty($card['money']))
                            $ {{ number_format((float)($cards[$key] ?? 0), 2) }}
                        @else
                            {{ number_format((int)($cards[$key] ?? 0)) }}
                        @endif
                    </strong>
                    <small>{{ $card['hint'] }}</small>
                </div>
            </a>
        @endforeach
    </div>

    <div class="lm-collection-report-section">
        <div class="lm-collection-report-section-head">
            <div>
                <h3>Reports</h3>
                <p>Choose a focused report to inspect details and filter records.</p>
            </div>
        </div>
        <div class="lm-collection-report-grid">
            @foreach($options['reports'] ?? [] as $key => $label)
                @php $meta = $reportMeta[$key] ?? ['icon' => 'fa fa-file-text-o', 'description' => 'Open this collection report for detailed records.']; @endphp
                <a class="lm-collection-report-card" href="{{ route('loan-management.collection.report', $key) }}">
                    <span class="lm-collection-report-card-icon"><i class="{{ $meta['icon'] }}"></i></span>
                    <span class="lm-collection-report-card-copy">
                        <strong>{{ $label }}</strong>
                        <small>{{ $meta['description'] }}</small>
                    </span>
                    <span class="lm-collection-report-card-action"><i class="fa fa-arrow-right"></i></span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endsection

@section('loan_css')
    <style>
        .lm-collection-report-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
            padding: 20px 22px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
        }
        .lm-collection-report-eyebrow {
            display: block;
            margin-bottom: 5px;
            color: #2563eb;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .lm-collection-report-hero h2 {
            margin: 0 0 6px;
            color: #111827;
            font-size: 24px;
            font-weight: 900;
            line-height: 1.15;
        }
        .lm-collection-report-hero p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }
        .lm-collection-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .lm-collection-summary-card,
        .lm-collection-report-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
        }
        .lm-collection-summary-card {
            display: flex;
            align-items: center;
            gap: 13px;
            min-height: 118px;
            padding: 16px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .lm-collection-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, .1);
        }
        .lm-collection-summary-icon,
        .lm-collection-report-card-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #fff;
        }
        .lm-collection-summary-icon {
            flex: 0 0 44px;
            width: 44px;
            height: 44px;
            font-size: 19px;
        }
        .lm-collection-summary-copy {
            min-width: 0;
        }
        .lm-collection-summary-copy span,
        .lm-collection-summary-copy small {
            display: block;
            color: #64748b;
            line-height: 1.25;
        }
        .lm-collection-summary-copy span {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .lm-collection-summary-copy strong {
            display: block;
            margin: 6px 0 4px;
            color: #111827;
            font-size: 21px;
            font-weight: 900;
            line-height: 1.1;
            word-break: break-word;
        }
        .lm-collection-summary-copy small {
            font-size: 12px;
            font-weight: 600;
        }
        .tone-blue .lm-collection-summary-icon { background: #2563eb; }
        .tone-green .lm-collection-summary-icon { background: #16a34a; }
        .tone-red .lm-collection-summary-icon { background: #dc2626; }
        .tone-orange .lm-collection-summary-icon { background: #ea580c; }
        .tone-violet .lm-collection-summary-icon { background: #7c3aed; }
        .tone-cyan .lm-collection-summary-icon { background: #0891b2; }
        .tone-slate .lm-collection-summary-icon { background: #475569; }
        .lm-collection-report-section {
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
        }
        .lm-collection-report-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .lm-collection-report-section-head h3 {
            margin: 0 0 3px;
            color: #111827;
            font-size: 18px;
            font-weight: 900;
        }
        .lm-collection-report-section-head p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }
        .lm-collection-report-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .lm-collection-report-card {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 92px;
            padding: 14px;
            color: #111827;
            text-decoration: none;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }
        .lm-collection-report-card:hover,
        .lm-collection-report-card:focus {
            border-color: #bfdbfe;
            box-shadow: 0 14px 32px rgba(37, 99, 235, .12);
            color: #111827;
            text-decoration: none;
            transform: translateY(-1px);
        }
        .lm-collection-report-card-icon {
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            background: #2563eb;
            font-size: 17px;
        }
        .lm-collection-report-card-copy {
            flex: 1;
            min-width: 0;
        }
        .lm-collection-report-card-copy strong,
        .lm-collection-report-card-copy small {
            display: block;
        }
        .lm-collection-report-card-copy strong {
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 900;
            line-height: 1.25;
        }
        .lm-collection-report-card-copy small {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.35;
        }
        .lm-collection-report-card-action {
            color: #94a3b8;
        }
        @media (max-width: 1400px) {
            .lm-collection-summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 991px) {
            .lm-collection-summary-grid,
            .lm-collection-report-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 767px) {
            .lm-collection-report-hero {
                align-items: stretch;
                flex-direction: column;
                padding: 16px;
            }
            .lm-collection-summary-grid,
            .lm-collection-report-grid {
                grid-template-columns: 1fr;
            }
            .lm-collection-summary-card {
                min-height: 96px;
            }
        }
    </style>
@endsection
