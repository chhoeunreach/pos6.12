@php
    $staffName = $report->staff_name ?: $report->seller_name;
    $avatar = !empty($report->staff_avatar) ? ltrim($report->staff_avatar, '/') : null;
    $avatarUrl = $avatar ? asset('uploads/avatar/' . rawurlencode($avatar)) : null;
@endphp

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">Sell List Detail - {{ $report->invoice_no ?? 'No invoice' }}</h4>
        </div>
        <div class="modal-body">
            <div class="sell-list-detail-head">
                <div class="sell-list-detail-avatar">
                    @if (!empty($avatarUrl))
                        <img src="{{ $avatarUrl }}" alt="{{ $staffName }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                    @endif
                    <span @if (!empty($avatarUrl)) style="display:none;" @endif>{{ strtoupper(mb_substr($staffName ?: 'S', 0, 1)) }}</span>
                </div>
                <div>
                    <div class="tw-font-bold tw-text-slate-900">{{ $staffName }}</div>
                    <div class="tw-text-xs tw-text-slate-500">{{ $report->staff_code ?: 'Staff' }} @if(!empty($report->branch_name)) / {{ $report->branch_name }} @endif</div>
                </div>
            </div>

            <div class="row tw-mt-3">
                <div class="col-sm-6">
                    <strong>Invoice:</strong> {{ $report->invoice_no ?? '-' }}<br>
                    <strong>Original Invoice:</strong> {{ $report->original_invoice_no ?? '-' }}<br>
                    <strong>Date:</strong> {{ @format_datetime($report->created_at) }}
                </div>
                <div class="col-sm-6">
                    <strong>Customer:</strong> {{ $report->customer_name ?: '-' }}<br>
                    <strong>Phone:</strong> {{ $report->customer_phone ?: '-' }}<br>
                    <strong>Total:</strong> {{ number_format((float) $report->total_amount, 2) }}<br>
                    <strong>Sell Type:</strong>
                    <span class="sell-type-display">{{ $report->service_type ?? 'លក់' }}</span>
                    <button type="button" class="btn btn-xs btn-link sell-type-edit-btn" data-report-id="{{ $report->id }}" style="padding:0 4px; vertical-align:baseline;">
                        <i class="fa fa-pencil"></i>
                    </button>
                    <select class="form-control sell-type-edit-select" style="display:none; width:auto; display:inline-block; height:auto; padding:0 4px; font-size:12px; width:120px;" data-report-id="{{ $report->id }}">
                    </select>
                </div>
            </div>

            <div class="table-responsive tw-mt-4">
                <table class="table table-condensed table-bordered">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Serial / IMEI</th>
                            <th>Model</th>
                            <th>Qty</th>
                            <th class="text-right">Price</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report->lines as $line)
                            @php
                                $serials = collect([$line->serial_number, $line->imei, $line->imei2, $line->primary_identifier])
                                    ->filter()
                                    ->unique()
                                    ->implode(' / ');
                            @endphp
                            <tr>
                                <td>{{ $line->product_name }}</td>
                                <td>{{ $line->sku ?: '-' }}</td>
                                <td>{{ $serials ?: '-' }}</td>
                                <td>{{ $line->model_number ?: '-' }}</td>
                                <td>{{ $line->qty }}</td>
                                <td class="text-right">{{ number_format((float) $line->unit_price, 2) }}</td>
                                <td class="text-right">{{ number_format((float) $line->subtotal, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (!empty($report->photos) && count($report->photos) > 0)
                <div class="tw-mt-4">
                    <strong>Photos:</strong>
                    <div class="sell-list-photo-grid">
                        @foreach ($report->photos as $photo)
                            @php
                                $photoUrl = $photo->photo_url ?: rtrim(env('HR_APP_URL', config('app.url')), '/') . '/storage/' . ltrim($photo->photo_path, '/');
                                $ocrPhotoUrl = action([\App\Http\Controllers\SellPosController::class, 'getHrSellListPhoto'], [$photo->id]);
                            @endphp
                            <button type="button" class="sell-list-photo-thumb" data-photo-url="{{ $ocrPhotoUrl }}" data-photo-fallback-url="{{ $photoUrl }}" data-photo-name="{{ $photo->original_name ?: 'Photo' }}">
                                <img src="{{ $photoUrl }}" alt="{{ $photo->original_name ?: 'Sell Out photo' }}">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!empty($report->note))
                <div class="tw-mt-2">
                    <strong>Note:</strong><br>
                    {{ $report->note }}
                </div>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>
    </div>
</div>
