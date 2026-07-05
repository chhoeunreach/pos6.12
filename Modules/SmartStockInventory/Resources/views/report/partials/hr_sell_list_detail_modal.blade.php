@php
    $staffName = $report->staff_name ?: $report->seller_name;
    $avatar = !empty($report->staff_avatar) ? ltrim($report->staff_avatar, '/') : null;
    $avatarUrl = $avatar ? asset('uploads/avatar/' . rawurlencode($avatar)) : null;
@endphp

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">HR Sell List Detail - {{ $report->invoice_no ?? 'No invoice' }}</h4>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-sm-6">
                    <div class="tw-flex tw-items-center tw-gap-2 tw-mb-3">
                        <div class="tw-w-10 tw-h-10 tw-rounded-full tw-bg-sky-100 tw-flex tw-items-center tw-justify-center tw-text-sky-700 tw-font-bold tw-text-sm">
                            @if (!empty($avatarUrl))
                                <img src="{{ $avatarUrl }}" alt="{{ $staffName }}" class="tw-w-10 tw-h-10 tw-rounded-full tw-object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            @endif
                            <span @if (!empty($avatarUrl)) style="display:none;" @endif>{{ strtoupper(mb_substr($staffName ?: 'S', 0, 1)) }}</span>
                        </div>
                        <div>
                            <div class="tw-font-bold tw-text-slate-900">{{ $staffName }}</div>
                            <div class="tw-text-xs tw-text-slate-500">{{ $report->staff_code ?: 'Staff' }} @if(!empty($report->branch_name)) / {{ $report->branch_name }} @endif</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row tw-mt-2">
                <div class="col-sm-6">
                    <strong>Invoice:</strong> {{ $report->invoice_no ?? '-' }}<br>
                    <strong>Original Invoice:</strong> {{ $report->original_invoice_no ?? '-' }}<br>
                    <strong>Date:</strong> {{ @format_datetime($report->created_at) }}
                </div>
                <div class="col-sm-6">
                    <strong>Customer:</strong> {{ $report->customer_name ?: '-' }}<br>
                    <strong>Phone:</strong> {{ $report->customer_phone ?: '-' }}<br>
                    <strong>Total:</strong> {{ number_format((float) $report->total_amount, 2) }}<br>
                    <strong>Sell Type:</strong> {{ $report->service_type ?? 'លក់' }}
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
                                $ocrPhotoUrl = route('ssi.report.hr_sell_list_photo', [$photo->id]);
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
                    <p class="tw-text-slate-700">{{ $report->note }}</p>
                </div>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>
    </div>
</div>
