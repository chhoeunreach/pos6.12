@php
    $activeReports = $hr_sell_out_reports->filter(fn ($report) => !empty($report->has_active_lines));
    $addedReports = $hr_sell_out_reports->filter(fn ($report) => !empty($report->has_added_lines));
@endphp

<div class="sell-list-filter-toggle">
    <button type="button" class="sell-list-filter-btn">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
        <span>Filter</span>
        <svg class="sell-list-filter-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </button>
</div>
<div class="sell-list-filter-body" style="display:none;">
    <div class="sell-list-filter-row">
        <div class="sell-list-filter-field">
            <label>Date Range</label>
            <input type="text" class="sell-list-filter-daterange form-control" readonly placeholder="Select date range...">
            <input type="hidden" class="sell-list-filter-date-from" value="">
            <input type="hidden" class="sell-list-filter-date-to" value="">
        </div>
    </div>
</div>

<div class="sell-list-search-wrap">
    <i class="fa fa-search"></i>
    <input type="text" class="sell-list-search" placeholder="Search by serial, phone, invoice...">
</div>

<div class="sell-list-tabs">
    <button type="button" class="sell-list-tab is-active" data-target="active">Active</button>
    <button type="button" class="sell-list-tab" data-target="added">Added <span class="sell-list-added-count">{{ $addedReports->count() }}</span></button>
</div>

<div class="sell-list-pane sell-list-pane-active is-active">
    @forelse ($activeReports as $report)
        @include('sale_pos.partials.hr_sell_list_report_row', ['report' => $report, 'line_status' => 'active', 'show_copy_button' => true])
    @empty
        <div class="tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-3 tw-px-3 tw-text-sky-800 tw-text-xs md:tw-text-sm">
            <i class="fa fa-info-circle"></i>
            <span>No active Sell Out records found.</span>
        </div>
    @endforelse
</div>

<div class="sell-list-pane sell-list-pane-added">
    @forelse ($addedReports as $report)
        @include('sale_pos.partials.hr_sell_list_report_row', ['report' => $report, 'line_status' => 'added', 'show_copy_button' => false])
    @empty
        <div class="sell-list-added-empty tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-3 tw-px-3 tw-text-sky-800 tw-text-xs md:tw-text-sm">
            <i class="fa fa-info-circle"></i>
            <span>No added records yet.</span>
        </div>
    @endforelse
</div>
