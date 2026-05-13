@extends('layouts.app')
@section('title', 'Overdue / Late Payments')

@php
    $tabs = [
        'today_due' => 'Today Due',
        'late_loans' => 'Late Loans',
        'promise_to_pay' => 'Promise To Pay',
        'broken_promise' => 'Broken Promise',
        'follow_up_needed' => 'Follow Up Needed',
        'legal_blacklist' => 'Legal / Blacklist',
    ];
    $active = request('tab', 'today_due');
@endphp

@section('content')
<section class="content-header">
    <h1>Overdue / Late Payments</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <ul class="nav nav-tabs">
                @foreach($tabs as $key => $label)
                    <li class="{{ $active === $key ? 'active' : '' }}">
                        <a href="{{ route('loan-management.overdue.index', ['tab' => $key]) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="box-body">
            <p class="text-muted">Tab: <strong>{{ $tabs[$active] ?? 'Today Due' }}</strong></p>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Loan #</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Collector</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Data list will be loaded here based on selected overdue tab.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@include('loanmanagement::layouts.sidebar_focus')
@endsection
