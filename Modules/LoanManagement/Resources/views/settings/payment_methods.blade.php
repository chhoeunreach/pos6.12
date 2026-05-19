@extends('loanmanagement::layouts.app')
@section('title', 'Payment Methods')

@section('content_body')
@php
    $customPaymentKeys = collect(range(1, 7))->map(fn ($number) => 'custom_pay_'.$number);
    $methodUsage = collect($usage ?? []);
    $totalPayments = $methodUsage->sum('payments_count');
    $totalAmount = $methodUsage->sum('total_amount');
@endphp

<section class="content-header">
    <h1>Payment Methods</h1>
</section>

<section class="content">
    @if(session('status.msg'))
        <div class="alert alert-{{ !empty(session('status.success')) ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-credit-card"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">POS Methods</span>
                    <span class="info-box-number">{{ count($paymentTypes) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-map-marker"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Locations</span>
                    <span class="info-box-number">{{ $locations->count() }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-list"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Loan Payments</span>
                    <span class="info-box-number">{{ number_format($totalPayments) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
            <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Collected</span>
                    <span class="info-box-number">{{ number_format($totalAmount, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('loan-management.settings.payment-methods.update') }}">
        @csrf

        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title">Ultimate POS Payment Methods</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width: 170px;">Code</th>
                            <th>Display Name</th>
                            <th style="width: 150px;">Loan Payments</th>
                            <th style="width: 170px;">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentTypes as $code => $label)
                            @php
                                $rowUsage = $methodUsage->get($label, ['payments_count' => 0, 'total_amount' => 0]);
                            @endphp
                            <tr>
                                <td><code>{{ $code }}</code></td>
                                <td>{{ $label }}</td>
                                <td>{{ number_format($rowUsage['payments_count'] ?? 0) }}</td>
                                <td>{{ number_format((float) ($rowUsage['total_amount'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box box-success">
            <div class="box-header">
                <h3 class="box-title">Loan Payment Usage</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Saved Method</th>
                            <th style="width: 150px;">Payments</th>
                            <th style="width: 170px;">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($methodUsage as $methodName => $row)
                            <tr>
                                <td>{{ $methodName }}</td>
                                <td>{{ number_format($row['payments_count'] ?? 0) }}</td>
                                <td>{{ number_format((float) ($row['total_amount'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No loan payment usage found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">Custom Payment Labels</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    @foreach($customPaymentKeys as $key)
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label>{{ ucwords(str_replace('_', ' ', $key)) }}</label>
                                <input type="text"
                                       name="custom_labels[{{ $key }}]"
                                       class="form-control"
                                       value="{{ $customLabels['payments'][$key] ?? '' }}"
                                       placeholder="{{ $paymentTypes[$key] ?? '' }}">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header">
                <h3 class="box-title">Available By Location</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="min-width: 180px;">Location</th>
                            @foreach($paymentTypes as $code => $label)
                                @if($code !== 'advance')
                                    <th class="text-center">{{ $label }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $location)
                            @php
                                $accounts = json_decode($location->default_payment_accounts ?? '[]', true) ?: [];
                            @endphp
                            <tr>
                                <td>
                                    {{ $location->name }}
                                    @if(!empty($location->location_id))
                                        <small class="text-muted">({{ $location->location_id }})</small>
                                    @endif
                                </td>
                                @foreach($paymentTypes as $code => $label)
                                    @if($code !== 'advance')
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   name="enabled[{{ $location->id }}][{{ $code }}]"
                                                   value="1"
                                                   {{ !empty($accounts[$code]['is_enabled']) ? 'checked' : '' }}>
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(1, count($paymentTypes)) }}" class="text-center text-muted">No business locations found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Save Payment Methods
                </button>
            </div>
        </div>
    </form>

    @if($legacyRows->isNotEmpty())
        <div class="box box-warning">
            <div class="box-header">
                <h3 class="box-title">Legacy Payment Method Data</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width:80px;">ID</th>
                            <th>Name</th>
                            <th style="width:120px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($legacyRows as $row)
                            <tr>
                                <td>{{ $row->id ?? '-' }}</td>
                                <td>{{ $row->name ?? '-' }}</td>
                                <td>
                                    @if(isset($row->is_active))
                                        <span class="label label-{{ !empty($row->is_active) ? 'success' : 'default' }}">
                                            {{ !empty($row->is_active) ? 'Active' : 'Inactive' }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
@endsection
