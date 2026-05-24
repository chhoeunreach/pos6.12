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
        .loan-asset-field { display: flex; gap: 6px; align-items: center; }
        .loan-asset-field input[type="file"] { flex: 1; min-width: 0; }
        .loan-asset-preview { margin-top: 8px; min-height: 48px; }
        .loan-asset-preview img { max-width: 96px; max-height: 64px; border: 1px solid #ddd; border-radius: 4px; padding: 2px; background: #fff; }
        .loan-asset-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
            gap: 10px;
            max-height: 60vh;
            overflow: auto;
        }
        .loan-asset-gallery-item {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            padding: 6px;
            text-align: left;
        }
        .loan-asset-gallery-item:hover { border-color: #3c8dbc; }
        .loan-asset-gallery-item img {
            width: 100%;
            height: 86px;
            object-fit: contain;
            background: #f7f7f7;
            border-radius: 4px;
        }
        .loan-asset-gallery-name {
            display: block;
            margin-top: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
        }
        .loan-asset-gallery-date { display: block; color: #777; font-size: 11px; }
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

    @component('components.filters', ['title' => __('report.filters')])
        <form method="GET" action="{{ route('loan-management.locations.index') }}" id="loan_location_filter_form">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="{{ $filters['name'] ?? '' }}" placeholder="Location name">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Location ID</label>
                        <input type="text" name="location_code" class="form-control" value="{{ $filters['location_code'] ?? '' }}" placeholder="Location ID">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ $filters['phone'] ?? '' }}" placeholder="Phone">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All</option>
                            <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 text-right">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Apply</button>
                    <a href="{{ route('loan-management.locations.index') }}" class="btn btn-default">Reset</a>
                </div>
            </div>
        </form>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => 'All Loan Locations'])
        @slot('tool')
            <div class="box-tools">
                <a href="{{ route('loan-management.locations.template') }}"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-info pull-right tw-mb-2 tw-mr-2">
                    <i class="fa fa-download"></i> Template
                </a>
                <a href="{{ route('loan-management.locations.export') }}"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-success pull-right tw-mb-2 tw-mr-2">
                    <i class="fa fa-file-excel-o"></i> Export
                </a>
                <button type="button"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-warning pull-right tw-mb-2 tw-mr-2"
                    data-toggle="modal" data-target="#loan_location_import_modal">
                    <i class="fa fa-upload"></i> Import
                </button>
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

    <div class="modal fade" id="loan_location_import_modal" tabindex="-1" role="dialog" aria-labelledby="loanLocationImportModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('loan-management.locations.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="loanLocationImportModalLabel">Import Loan Locations</h4>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            Required column: <code>name</code><br>
                            Optional columns: <code>location_code</code>, <code>loan_invoice_prefix</code>, <code>address</code>, <code>phone</code>, <code>status</code>
                        </div>
                        <div class="form-group">
                            <label>Import File</label>
                            <input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx" required>
                            <p class="help-block">Use the template file for best results.</p>
                        </div>
                        <div class="form-group">
                            <label>Duplicate Records</label>
                            <select name="duplicate_mode" class="form-control">
                                <option value="skip">Skip duplicate</option>
                                <option value="replace">Replace existing</option>
                            </select>
                            <p class="help-block">Existing records match by <code>location_code</code> first, then by <code>name</code>.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Import</button>
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
                                    <div class="loan-asset-field">
                                        <input type="file" name="logo" class="form-control" accept="image/*">
                                        <button type="button" class="btn btn-default loan-asset-gallery-open" title="Choose from gallery" data-target-input="logo_existing_{{ $location->id }}" data-preview="logo_preview_{{ $location->id }}">
                                            <i class="fa fa-picture-o"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="logo_existing" id="logo_existing_{{ $location->id }}">
                                    <div class="loan-asset-preview" id="logo_preview_{{ $location->id }}">
                                        @if(! empty($location->logo_asset_url))
                                            <img src="{{ $location->logo_asset_url }}" alt="Logo">
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Payment QR Code</label>
                                    <div class="loan-asset-field">
                                        <input type="file" name="payment_qr" class="form-control" accept="image/*">
                                        <button type="button" class="btn btn-default loan-asset-gallery-open" title="Choose from gallery" data-target-input="payment_qr_existing_{{ $location->id }}" data-preview="payment_qr_preview_{{ $location->id }}">
                                            <i class="fa fa-picture-o"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="payment_qr_existing" id="payment_qr_existing_{{ $location->id }}">
                                    <div class="loan-asset-preview" id="payment_qr_preview_{{ $location->id }}">
                                        @if(! empty($location->payment_qr_asset_url))
                                            <img src="{{ $location->payment_qr_asset_url }}" alt="Payment QR Code">
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Telegram QR Code</label>
                                    <div class="loan-asset-field">
                                        <input type="file" name="telegram_qr" class="form-control" accept="image/*">
                                        <button type="button" class="btn btn-default loan-asset-gallery-open" title="Choose from gallery" data-target-input="telegram_qr_existing_{{ $location->id }}" data-preview="telegram_qr_preview_{{ $location->id }}">
                                            <i class="fa fa-picture-o"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="telegram_qr_existing" id="telegram_qr_existing_{{ $location->id }}">
                                    <div class="loan-asset-preview" id="telegram_qr_preview_{{ $location->id }}">
                                        @if(! empty($location->telegram_qr_asset_url))
                                            <img src="{{ $location->telegram_qr_asset_url }}" alt="Telegram QR Code">
                                        @endif
                                    </div>
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

    <div class="modal fade" id="loan_asset_gallery_modal" tabindex="-1" role="dialog" aria-labelledby="loanAssetGalleryModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="loanAssetGalleryModalLabel">Choose Existing Image</h4>
                </div>
                <div class="modal-body">
                    @if(! empty($assetGallery))
                        <div class="loan-asset-gallery-grid">
                            @foreach($assetGallery as $asset)
                                <button type="button" class="loan-asset-gallery-item" data-path="{{ $asset['path'] }}" data-url="{{ $asset['url'] }}">
                                    <img src="{{ $asset['url'] }}" alt="{{ $asset['name'] }}" loading="lazy" onerror="this.style.display='none';">
                                    <span class="loan-asset-gallery-name" title="{{ $asset['name'] }}">{{ $asset['name'] }}</span>
                                    <span class="loan-asset-gallery-date">{{ $asset['modified'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info" style="margin-bottom:0;">No existing images found yet.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('loan_js')
    <script>
        (function($) {
            var galleryTargetInput = null;
            var galleryPreview = null;
            var galleryFileInput = null;

            $(function() {
                if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loan_location_table')) {
                    $('#loan_location_table').DataTable({
                        pageLength: parseInt(window.__default_datatable_page_entries || 25, 10),
                        order: [[0, 'asc']],
                        columnDefs: [
                            { targets: [5, 7], orderable: false }
                        ]
                    });
                }
            });

            $(document).on('click', '.loan-asset-gallery-open', function() {
                galleryTargetInput = $('#' + $(this).data('target-input'));
                galleryPreview = $('#' + $(this).data('preview'));
                galleryFileInput = $(this).closest('.loan-asset-field').find('input[type="file"]');
                $('#loan_asset_gallery_modal').modal('show');
            });

            $(document).on('click', '.loan-asset-gallery-item', function() {
                var path = $(this).data('path');
                var url = $(this).data('url');

                if (galleryTargetInput && galleryTargetInput.length) {
                    galleryTargetInput.val(path);
                }
                if (galleryPreview && galleryPreview.length) {
                    galleryPreview.html('<img src="' + url + '" alt="Selected image">');
                }
                if (galleryFileInput && galleryFileInput.length) {
                    galleryFileInput.val('');
                }

                $('#loan_asset_gallery_modal').modal('hide');
            });

            $(document).on('change', '.loan-asset-field input[type="file"]', function() {
                $(this).closest('.form-group').find('input[type="hidden"]').val('');
            });
        })(jQuery);
    </script>
@endsection
