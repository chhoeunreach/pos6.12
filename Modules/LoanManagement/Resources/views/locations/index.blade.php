@extends('loanmanagement::layouts.app')
@section('title', 'Loan Locations')
@section('content_body')
<section class="content-header">
    <h1>Loan Locations</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">Print Assets By Location</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Code</th>
                        <th>Logo</th>
                        <th>Payment QR</th>
                        <th>Telegram QR</th>
                        <th style="width:460px;">Invoice, Images & Telegram</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($locations as $location)
                        <tr>
                            <td>
                                <strong>{{ $location->name ?? '-' }}</strong><br>
                                <small class="text-muted">{{ $location->address ?? '' }}</small>
                            </td>
                            <td>{{ $location->location_code ?? '-' }}</td>
                            <td>
                                @if(! empty($location->logo_asset_url))
                                    <img src="{{ $location->logo_asset_url }}" alt="Logo" style="max-height:44px;max-width:90px;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                                    <span class="text-muted" style="display:none;">Not set</span>
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                            <td>
                                @if(! empty($location->payment_qr_asset_url))
                                    <img src="{{ $location->payment_qr_asset_url }}" alt="Payment QR" style="max-height:54px;max-width:54px;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                                    <span class="text-muted" style="display:none;">Not set</span>
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                            <td>
                                @if(! empty($location->telegram_qr_asset_url))
                                    <img src="{{ $location->telegram_qr_asset_url }}" alt="Telegram QR" style="max-height:54px;max-width:54px;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                                    <span class="text-muted" style="display:none;">Not set</span>
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                            <td>
                                {!! Form::open(['url' => route('loan-management.locations.assets.update', $location->id), 'method' => 'post', 'files' => true]) !!}
                                <div class="form-group">
                                    <label>Loan Invoice Prefix</label>
                                    <input type="text" name="loan_invoice_prefix" class="form-control input-sm" value="{{ $location->loan_invoice_prefix ?? '' }}" maxlength="50" placeholder="e.g. LN, PP, SR">
                                    @php
                                        $invoicePrefixExample = rtrim(trim((string) ($location->loan_invoice_prefix ?? '')), '-/');
                                        $invoicePrefixExample = $invoicePrefixExample !== '' ? $invoicePrefixExample : 'LN';
                                    @endphp
                                    <small class="text-muted">Auto loan invoice example: {{ $invoicePrefixExample }}-{{ date('Ymd') }}-000001</small>
                                </div>
                                <hr style="margin:10px 0;">
                                <div class="form-group">
                                    <label>Logo</label>
                                    <input type="file" name="logo" class="form-control input-sm" accept="image/*">
                                </div>
                                <div class="form-group">
                                    <label>Payment QR Code</label>
                                    <input type="file" name="payment_qr" class="form-control input-sm" accept="image/*">
                                </div>
                                <div class="form-group">
                                    <label>Telegram QR Code</label>
                                    <input type="file" name="telegram_qr" class="form-control input-sm" accept="image/*">
                                </div>
                                <hr style="margin:10px 0;">
                                <div class="form-group">
                                    <label>Payment Telegram Chat ID</label>
                                    <input type="text" name="telegram_payment_chat_id" class="form-control input-sm" value="{{ $location->telegram_payment_chat_id ?? '' }}" placeholder="-100xxxxxxxxxx">
                                    <div class="checkbox" style="margin-top:6px;margin-bottom:0;">
                                        <label>
                                            <input type="checkbox" name="telegram_notify_payment" value="1" {{ ! empty($location->telegram_notify_payment) ? 'checked' : '' }}>
                                            Send Telegram when payment is received
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Installment Telegram Chat ID</label>
                                    <input type="text" name="telegram_installment_chat_id" class="form-control input-sm" value="{{ $location->telegram_installment_chat_id ?? '' }}" placeholder="-100xxxxxxxxxx">
                                    <div class="checkbox" style="margin-top:6px;margin-bottom:0;">
                                        <label>
                                            <input type="checkbox" name="telegram_notify_installment" value="1" {{ ! empty($location->telegram_notify_installment) ? 'checked' : '' }}>
                                            Send Telegram when installment loan is created
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fa fa-save"></i> Save
                                </button>
                                {!! Form::close() !!}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No locations found. Create a loan from POS sale first or run location sync.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
