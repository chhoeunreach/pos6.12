@php
    $staffName = $report->staff_name ?: $report->seller_name;
    $avatar = !empty($report->staff_avatar) ? ltrim($report->staff_avatar, '/') : null;
    $avatarUrl = $avatar ? asset('uploads/avatar/' . rawurlencode($avatar)) : null;
    $visibleLines = $report->lines->filter(fn ($line) => $line->pos_serial_status === $line_status);
    $rowStaffCode = $report->staff_code ?? '';
    $rowSellerName = $report->seller_name ?? '';
    $rowCustomerPhone = $report->customer_phone ?? '';
@endphp

<div class="sell-list-report-row no-print" data-report-id="{{ $report->id }}"
     data-staff-code="{{ $rowStaffCode }}"
     data-staff-name="{{ $staffName }}"
     data-seller-name="{{ $rowSellerName }}"
     data-customer-phone="{{ $rowCustomerPhone }}">
    <div class="tw-flex tw-items-center tw-gap-2 tw-mb-1">
        <div class="tw-flex tw-items-center tw-gap-1.5 tw-flex-shrink-0 tw-min-w-0 tw-max-w-[35%]">
            <div class="sell-list-avatar-wrap tw-flex-shrink-0">
                @if (!empty($avatarUrl))
                    <img class="sell-list-avatar" src="{{ $avatarUrl }}" alt="{{ $staffName }}" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('tw-hidden');">
                @endif
                <span class="sell-list-avatar-fallback @if (!empty($avatarUrl)) tw-hidden @endif">{{ strtoupper(mb_substr($staffName ?: 'S', 0, 1)) }}</span>
            </div>
            <div class="tw-min-w-0">
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

        <div class="tw-min-w-0 tw-flex-1 tw-text-center">
            <div class="tw-font-bold tw-text-slate-800 tw-text-xs tw-leading-4 tw-truncate" title="Invoice: {{ $report->invoice_no ?? 'N/A' }} | Phone: {{ $report->customer_phone ?? 'N/A' }} | Customer: {{ $report->customer_name ?? 'N/A' }} | Staff: {{ $staffName }}">
                {{ $report->invoice_no ?? 'No invoice' }}
            </div>
            <div class="tw-text-[10px] tw-text-slate-500 tw-leading-3 tw-truncate">
                {{ $report->customer_phone ?: 'No phone' }}
                @if (!empty($report->customer_name))
                    <span class="tw-text-slate-300">/</span> {{ $report->customer_name }}
                @endif
            </div>
            <div class="tw-text-[9px] tw-text-slate-400 tw-leading-3">
                {{ @format_datetime($report->created_at) }}
            </div>
        </div>

        <div class="tw-flex tw-items-center tw-gap-1 tw-flex-shrink-0">
            @if (!empty($show_copy_button))
                <button type="button" class="sell-list-copy-all-btn" data-report-id="{{ $report->id }}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copy
                </button>
                <button type="button" class="sell-list-add-all-btn" data-report-id="{{ $report->id }}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg>
                    Add to
                </button>
            @endif
            <button type="button" class="sell-list-detail-btn" data-report-id="{{ $report->id }}">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Detail
            </button>
        </div>
    </div>

    <div class="sell-list-products">
        @forelse ($visibleLines as $line)
            @php
                $serials = collect([$line->serial_number, $line->imei, $line->imei2, $line->primary_identifier])
                    ->filter()
                    ->unique()
                    ->values();
            @endphp
            @php
                $primarySerial = trim($line->serial_number ?: $line->imei ?: $line->imei2 ?: $line->primary_identifier ?: $line->sku ?: '');
            @endphp
            <div class="sell-list-product-line" data-line-id="{{ $line->id }}" data-status="{{ $line->pos_serial_status }}" data-serial="{{ $primarySerial }}" data-unit-price="{{ $line->unit_price }}">
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
                    @if (!empty($show_copy_button))
                        <div class="tw-flex tw-items-center tw-justify-end tw-gap-1 tw-mt-1">
                            <button type="button" class="sell-list-add-to-btn tw-text-[10px] tw-font-semibold tw-text-emerald-600 hover:tw-text-emerald-800 tw-bg-transparent tw-border-0 tw-cursor-pointer tw-px-1" data-line-id="{{ $line->id }}" data-serial="{{ $primarySerial }}">Add to</button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="tw-text-[11px] tw-text-slate-500 tw-py-1">No products found.</div>
        @endforelse
    </div>
</div>
