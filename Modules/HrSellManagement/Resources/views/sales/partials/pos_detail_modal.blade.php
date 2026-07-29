@php
    $staffName = $report->staff_name ?: $report->seller_name;
    $avatar = ! empty($report->staff_avatar) ? ltrim($report->staff_avatar, '/') : null;
    $avatarUrl = $avatar ? asset('uploads/avatar/' . rawurlencode($avatar)) : null;
@endphp

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">POS HR Sell Detail - {{ $report->invoice_no ?? 'No invoice' }}</h4>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-sm-6">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <div style="width:42px;height:42px;border-radius:50%;background:#e8f4fb;color:#2c7fb0;display:flex;align-items:center;justify-content:center;font-weight:bold;overflow:hidden;">
                            @if(! empty($avatarUrl))
                                <img src="{{ $avatarUrl }}" alt="{{ $staffName }}" style="width:42px;height:42px;object-fit:cover;border-radius:50%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            @endif
                            <span @if(! empty($avatarUrl)) style="display:none;" @endif>{{ strtoupper(mb_substr($staffName ?: 'S', 0, 1)) }}</span>
                        </div>
                        <div>
                            <strong>{{ $staffName ?: '-' }}</strong><br>
                            <small class="text-muted">{{ $report->staff_code ?: 'Staff' }} @if(! empty($report->branch_name)) / {{ $report->branch_name }} @endif</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-6">
                    <strong>Invoice:</strong> {{ $report->invoice_no ?? '-' }}<br>
                    <strong>Original Invoice:</strong> {{ $report->original_invoice_no ?? '-' }}<br>
                    <strong>Date:</strong> {{ @format_datetime($report->created_at) }}
                </div>
                <div class="col-sm-6">
                    <strong>Customer:</strong> {{ $report->customer_name ?: '-' }}<br>
                    <strong>Phone:</strong> {{ $report->customer_phone ?: '-' }}<br>
                    <strong>Total:</strong> {{ number_format((float) $report->total_amount, 2) }}<br>
                    <strong>Sell Type:</strong> {{ in_array($report->service_type, ['sell', 'លក់']) ? 'Sell / លក់' : ($report->service_type ?: '-') }}
                </div>
            </div>

            <div class="table-responsive" style="margin-top:18px;">
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
                        @forelse($report->lines as $line)
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
                            <tr><td colspan="7" class="text-center text-muted">No products found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(! empty($report->photos) && count($report->photos) > 0)
                <div style="margin-top:18px;">
                    <strong>Photos:</strong>
                    <div class="hr-sell-photo-grid">
                        @foreach($report->photos as $photo)
                            @php
                                $photoUrl = $photo->photo_url ?: rtrim(env('HR_APP_URL', config('app.url')), '/') . '/storage/' . ltrim($photo->photo_path, '/');
                                $proxyUrl = route('hr-sell.sales.pos_photo', [$photo->id]);
                            @endphp
                            <button type="button" class="hr-sell-photo-thumb" data-photo-url="{{ $proxyUrl }}" data-photo-fallback-url="{{ $photoUrl }}" data-photo-name="{{ $photo->original_name ?: 'Sell photo' }}">
                                <img src="{{ $proxyUrl }}" alt="{{ $photo->original_name ?: 'Sell photo' }}" onerror="this.src='{{ $photoUrl }}'; this.onerror=null;">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(! empty($report->note))
                <div style="margin-top:14px;">
                    <strong>Note:</strong><br>
                    {{ $report->note }}
                </div>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
        </div>
    </div>
</div>
