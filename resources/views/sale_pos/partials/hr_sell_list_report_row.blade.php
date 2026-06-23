@php
    $staffName = $report->staff_name ?: $report->seller_name;
    $avatar = !empty($report->staff_avatar) ? ltrim($report->staff_avatar, '/') : null;
    $avatarBase = rtrim(env('HR_APP_URL', config('app.url')), '/');
    $avatarUrls = $avatar ? [
        $avatarBase . '/storage/' . $avatar,
        $avatarBase . '/uploads/' . $avatar,
        $avatarBase . '/storage/profile/' . $avatar,
        $avatarBase . '/uploads/profile/' . $avatar,
    ] : [];
    $visibleLines = $report->lines->filter(fn ($line) => $line->pos_serial_status === $line_status);
@endphp

<div class="sell-list-report-row no-print" data-report-id="{{ $report->id }}">
    <div class="sell-list-staff-head">
        <div class="sell-list-avatar-wrap">
            @if (!empty($avatarUrls))
                <img class="sell-list-avatar" src="{{ $avatarUrls[0] }}" data-fallbacks='@json(array_slice($avatarUrls, 1))' alt="{{ $staffName }}">
            @endif
            <span class="sell-list-avatar-fallback @if (!empty($avatarUrls)) tw-hidden @endif">{{ strtoupper(mb_substr($staffName ?: 'S', 0, 1)) }}</span>
        </div>
        <div class="tw-min-w-0 tw-flex-1">
            <div class="tw-font-bold tw-text-slate-800 tw-text-xs tw-leading-4 tw-truncate" title="{{ $staffName }}">
                {{ $staffName }}
            </div>
            <div class="tw-text-[10px] tw-text-slate-500 tw-leading-3 tw-truncate">
                {{ $report->staff_code ?: 'Staff' }}
                @if (!empty($report->branch_name))
                    <span class="tw-text-slate-300">/</span> {{ $report->branch_name }}
                @endif
            </div>
        </div>
    </div>

    <div class="tw-flex tw-items-start tw-justify-between tw-gap-2">
        <div class="tw-min-w-0">
            <div class="tw-font-bold tw-text-slate-800 tw-text-xs tw-leading-4 tw-truncate">
                {{ $report->invoice_no ?? 'No invoice' }}
            </div>
            <div class="tw-text-[10px] tw-text-slate-500 tw-leading-3 tw-truncate">
                {{ $report->customer_phone ?: 'No phone' }}
                @if (!empty($report->customer_name))
                    <span class="tw-text-slate-300">/</span> {{ $report->customer_name }}
                @endif
            </div>
        </div>
        @if (!empty($show_copy_button))
            <button type="button" class="sell-list-add-btn" data-report-id="{{ $report->id }}">Copy Serial Number</button>
        @endif
    </div>

    <div class="sell-list-products">
        @forelse ($visibleLines as $line)
            @php
                $serials = collect([$line->serial_number, $line->imei, $line->imei2, $line->primary_identifier])
                    ->filter()
                    ->unique()
                    ->values();
            @endphp
            <div class="sell-list-product-line" data-line-id="{{ $line->id }}" data-status="{{ $line->pos_serial_status }}">
                <div class="tw-min-w-0 tw-flex-1">
                    <div class="tw-text-xs tw-font-semibold tw-text-slate-700 tw-leading-4 tw-truncate">
                        {{ $line->product_name }}
                    </div>
                    <div class="tw-text-[10px] tw-text-slate-500 tw-leading-3 tw-truncate">
                        @if ($serials->isNotEmpty())
                            SN: {{ $serials->implode(' / ') }}
                        @else
                            SN: -
                        @endif
                    </div>
                </div>
                <div class="tw-text-right tw-flex-shrink-0">
                    <div class="tw-text-[10px] tw-text-slate-500 tw-leading-3">{{ ucfirst($line->pos_serial_status) }}</div>
                    <div class="tw-text-xs tw-font-bold tw-text-slate-800">{{ number_format((float) $line->unit_price, 2) }}</div>
                </div>
            </div>
        @empty
            <div class="tw-text-[11px] tw-text-slate-500 tw-py-1">No products found.</div>
        @endforelse
    </div>
</div>
