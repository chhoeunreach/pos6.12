@extends('loanmanagement::layouts.app')
@section('title', 'Loan Locations')

@section('loan_css')
    <style>
        .loan-location-thumb { max-height: 44px; max-width: 90px; }
        .loan-location-qr { max-height: 54px; max-width: 54px; }
        .loan-location-actions .btn { margin: 0 3px 4px 0; }
        .text-ellipsis {
            display: block;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
@endsection

@section('content_body')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Loan Locations
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Manage loan branches, invoice prefixes, print assets, and Telegram routing</small>
    </h1>
</section>

<section class="content">
    @if(session('status'))
        @php $status = session('status'); @endphp
        <div class="alert alert-{{ !empty($status['success']) ? 'success' : 'danger' }}">
            {{ $status['msg'] ?? '' }}
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger">
            <ul style="margin-bottom:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @component('components.widget', ['class' => 'box-primary', 'title' => 'All Loan Locations'])
        @slot('tool')
            <div class="box-tools">
                <button type="button"
                    class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right tw-mb-2"
                    data-toggle="modal" data-target="#loan_location_add_modal">
                    <i class="fa fa-plus"></i> Add
                </button>
            </div>
        @endslot

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="loan_location_table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location ID</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Loan Invoice Prefix</th>
                        <th>Assets</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($locations as $location)
                        @php
                            $invoicePrefixExample = rtrim(trim((string) ($location->loan_invoice_prefix ?? '')), '-/');
                            $invoicePrefixExample = $invoicePrefixExample !== '' ? $invoicePrefixExample : 'LN';
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $location->name ?? '-' }}</strong>
                                @if(! empty($location->main_location_id))
                                    <br><small class="text-muted">POS Location #{{ $location->main_location_id }}</small>
                                @endif
                            </td>
                            <td>{{ $location->location_code ?? '-' }}</td>
                            <td><span class="text-ellipsis" title="{{ $location->address ?? '' }}">{{ $location->address ?? '-' }}</span></td>
                            <td>{{ $location->phone ?? '-' }}</td>
                            <td>
                                {{ $location->loan_invoice_prefix ?? '-' }}<br>
                                <small class="text-muted">{{ $invoicePrefixExample }}-{{ date('Ymd') }}-000001</small>
                            </td>
                            <td>
                                @if(! empty($location->logo_asset_url))
                                    <img src="{{ $location->logo_asset_url }}" alt="Logo" class="loan-location-thumb" onerror="this.style.display='none';">
                                @endif
                                @if(! empty($location->payment_qr_asset_url))
                                    <img src="{{ $location->payment_qr_asset_url }}" alt="Payment QR" class="loan-location-qr" onerror="this.style.display='none';">
                                @endif
                                @if(! empty($location->telegram_qr_asset_url))
                                    <img src="{{ $location->telegram_qr_asset_url }}" alt="Telegram QR" class="loan-location-qr" onerror="this.style.display='none';">
                                @endif
                                @if(empty($location->logo_asset_url) && empty($location->payment_qr_asset_url) && empty($location->telegram_qr_asset_url))
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                            <td>
                                <span class="label label-{{ ($location->status ?? 'active') === 'active' ? 'success' : 'default' }}">
                                    {{ ucfirst($location->status ?? 'active') }}
                                </span>
                            </td>
                            <td class="loan-location-actions">
                                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary" data-toggle="modal" data-target="#loan_location_edit_modal_{{ $location->id }}">
                                    <i class="glyphicon glyphicon-edit"></i> Edit
                                </button>
                                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info" data-toggle="modal" data-target="#loan_location_assets_modal_{{ $location->id }}">
                                    <i class="fa fa-image"></i> Assets
                                </button>
                                <form method="POST" action="{{ route('loan-management.locations.destroy', $location->id) }}" onsubmit="return confirm('Delete this location?');" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No locations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent

    <div class="modal fade" id="loan_location_add_modal" tabindex="-1" role="dialog" aria-labelledby="loanLocationAddModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('loan-management.locations.store') }}">
                    @csrf
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="loanLocationAddModalLabel">Add Loan Location</h4>
                    </div>
                    <div class="modal-body">
                        @include('loanmanagement::locations.partials.form', ['location' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Save</button>
                        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach($locations as $location)
        <div class="modal fade" id="loan_location_edit_modal_{{ $location->id }}" tabindex="-1" role="dialog" aria-labelledby="loanLocationEditModalLabel{{ $location->id }}">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <form method="POST" action="{{ route('loan-management.locations.update', $location->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="loanLocationEditModalLabel{{ $location->id }}">Edit Loan Location</h4>
                        </div>
                        <div class="modal-body">
                            @include('loanmanagement::locations.partials.form', ['location' => $location])
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Save</button>
                            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="loan_location_assets_modal_{{ $location->id }}" tabindex="-1" role="dialog" aria-labelledby="loanLocationAssetsModalLabel{{ $location->id }}">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    {!! Form::open(['url' => route('loan-management.locations.assets.update', $location->id), 'method' => 'post', 'files' => true]) !!}
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="loanLocationAssetsModalLabel{{ $location->id }}">Location Assets & Telegram</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Logo</label>
                                    <input type="file" name="logo" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Payment QR Code</label>
                                    <input type="file" name="payment_qr" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Telegram QR Code</label>
                                    <input type="file" name="telegram_qr" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="clearfix"></div>
                            <hr>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Payment Telegram Chat ID</label>
                                    <input type="text" name="telegram_payment_chat_id" class="form-control" value="{{ $location->telegram_payment_chat_id ?? '' }}" placeholder="-100xxxxxxxxxx">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="telegram_notify_payment" value="1" {{ ! empty($location->telegram_notify_payment) ? 'checked' : '' }}>
                                            Send Telegram when payment is received
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Installment Telegram Chat ID</label>
                                    <input type="text" name="telegram_installment_chat_id" class="form-control" value="{{ $location->telegram_installment_chat_id ?? '' }}" placeholder="-100xxxxxxxxxx">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="telegram_notify_installment" value="1" {{ ! empty($location->telegram_notify_installment) ? 'checked' : '' }}>
                                            Send Telegram when installment loan is created
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Save</button>
                        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">Close</button>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    @endforeach
</section>
@endsection
