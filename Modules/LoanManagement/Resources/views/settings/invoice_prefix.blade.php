@extends('layouts.app')
@section('title', 'Loan Settings')

@section('content')
<section class="content-header">
    <h1>Loan Settings</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">Business Location Compatibility Settings</h3>
        </div>
        <div class="box-body">
            <p class="text-muted">
                Required location fields are always safe: <strong>id, name, location_id</strong>.
                Optional fields are shown only when your POS database contains them.
            </p>
            @if(!$hasInvoicePrefix)
                <div class="alert alert-warning">
                    Your current Ultimate POS structure does not include <code>invoice_prefix</code>. Prefix editing is disabled for compatibility.
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
                                <th>Optional Metadata</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($locations as $location)
                                <tr>
                                    <td>{{ $location->id }}</td>
                                    <td>
                                        {{ $location->name }}
                                        @if(!empty($location->location_id))
                                            <small class="text-muted">({{ $location->location_id }})</small>
                                        @endif
                                    </td>
                                    @if($hasInvoicePrefix)
                                        <td>
                                            <input type="text"
                                                   name="invoice_prefixes[{{ $location->id }}]"
                                                   value="{{ $location->invoice_prefix ?? '' }}"
                                                   maxlength="50"
                                                   class="form-control"
                                                   placeholder="e.g. BR1, SHOP-A, PP">
                                        </td>
                                    @endif
                                    <td>
                                        @php
                                            $optional = [];
                                            if (isset($location->invoice_scheme_id)) $optional[] = 'invoice_scheme_id: '.$location->invoice_scheme_id;
                                            if (isset($location->receipt_printer_type) && $location->receipt_printer_type !== null && $location->receipt_printer_type !== '') $optional[] = 'receipt_printer_type: '.$location->receipt_printer_type;
                                            if (isset($location->mobile) && $location->mobile !== null && $location->mobile !== '') $optional[] = 'mobile: '.$location->mobile;
                                            if (isset($location->alternate_number) && $location->alternate_number !== null && $location->alternate_number !== '') $optional[] = 'alternate_number: '.$location->alternate_number;
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
