@extends('loanmanagement::layouts.app')
@section('title', 'Invoice Prefix')

@section('content_body')
<section class="content-header">
    <h1>Invoice Prefix</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">Business Location Compatibility Settings</h3>
        </div>
        <div class="box-body">
            <p class="text-muted">
                Configure loan invoice prefixes for each branch. These prefixes are used when creating standalone loan invoices.
            </p>
            @if(!$hasInvoicePrefix)
                <div class="alert alert-warning">
                    The loan location table does not include <code>loan_invoice_prefix</code>. Prefix editing is disabled.
                </div>
            @endif

            <form method="POST" action="{{ route('loan-management.settings.invoice-prefix') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Location</th>
                                @if($hasInvoicePrefix)
                                    <th style="width: 260px;">Invoice Prefix</th>
                                @endif
                                <th>Branch Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($locations as $location)
                                <tr>
                                    <td>{{ $location->id }}</td>
                                    <td>
                                        {{ $location->name }}
                                        @if(!empty($location->location_code))
                                            <small class="text-muted">({{ $location->location_code }})</small>
                                        @endif
                                    </td>
                                    @if($hasInvoicePrefix)
                                        <td>
                                            <input type="text"
                                                   name="invoice_prefixes[{{ $location->id }}]"
                                                   value="{{ $location->loan_invoice_prefix ?? '' }}"
                                                   maxlength="50"
                                                   class="form-control"
                                                   placeholder="e.g. BR1, SHOP-A, PP">
                                        </td>
                                    @endif
                                    <td>
                                        @php
                                            $optional = [];
                                            if (isset($location->phone) && $location->phone !== null && $location->phone !== '') $optional[] = 'phone: '.$location->phone;
                                            if (isset($location->address) && $location->address !== null && $location->address !== '') $optional[] = 'address: '.$location->address;
                                            if (isset($location->status) && $location->status !== null && $location->status !== '') $optional[] = 'status: '.$location->status;
                                        @endphp
                                        {{ !empty($optional) ? implode(' | ', $optional) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $hasInvoicePrefix ? 4 : 3 }}" class="text-center">No business locations found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($hasInvoicePrefix)
                    <button type="submit" class="btn btn-primary">
                        Save Prefix Settings
                    </button>
                @endif
            </form>
        </div>
    </div>
</section>
@endsection

@section('javascript')
@endsection
