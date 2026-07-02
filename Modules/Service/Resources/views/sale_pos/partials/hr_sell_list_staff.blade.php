@php
    $activeReports = $hr_sell_out_reports->filter(fn ($report) => !empty($report->has_active_lines));
    $addedReports = $hr_sell_out_reports->filter(fn ($report) => !empty($report->has_added_lines));
    $defaultDateFrom = $default_date_from ?? \Carbon\Carbon::now()->format('Y-m-d');
    $defaultDateTo = $default_date_to ?? \Carbon\Carbon::now()->format('Y-m-d');
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
            <input type="hidden" class="sell-list-filter-date-from" value="{{ $defaultDateFrom }}">
            <input type="hidden" class="sell-list-filter-date-to" value="{{ $defaultDateTo }}">
        </div>
    </div>
    <div class="sell-list-filter-row">
        <div class="sell-list-filter-field">
            <label>Branch</label>
            <select class="sell-list-filter-branch form-control">
                @php $defaultHrBranch = $default_hr_branch ?? ''; @endphp
                @if (!empty($hr_branches))
                    @foreach ($hr_branches as $branch)
                        <option value="{{ $branch }}" {{ $branch === $defaultHrBranch ? 'selected' : '' }}>{{ $branch }}</option>
                    @endforeach
                @endif
            </select>
        </div>
    </div>
    <div class="sell-list-filter-row">
        <div class="sell-list-filter-field">
            <label>Sell Type</label>
            <select class="sell-list-filter-sell-type form-control">
                <option value="លក់">Sell / លក់</option>
                @if (!empty($sell_types))
                    @foreach ($sell_types as $type)
                        @if (!in_array($type, ['sell', 'លក់']))
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endif
                    @endforeach
                @endif
            </select>
        </div>
    </div>
</div>

<div class="sell-list-search-wrap">
    <i class="fa fa-search"></i>
    <input type="text" class="sell-list-search" placeholder="Search by serial, phone, invoice...">
</div>

<div class="sell-list-pane-header">
    <span class="sell-list-pane-header-icon"><i class="fa fa-users"></i></span>
    <span class="sell-list-pane-header-title">Sell Out</span>
    <span class="sell-list-pane-header-source">data from HR</span>
</div>

<div class="sell-list-total-banner" title="Click to expand">
    <div class="sell-list-total-banner-row">
        <span class="sell-list-total-banner-label"><i class="fa fa-list"></i> Total Sell Out</span>
        <span class="sell-list-total-banner-value sell-list-total-total">{{ number_format((float)($total ?? 0)) }}</span>
    </div>
    <div class="sell-list-total-banner-detail" style="display:none;">
        <div class="sell-list-total-banner-row">
            <span class="sell-list-total-banner-label"><i class="fa fa-circle"></i> Active</span>
            <span class="sell-list-total-banner-value sell-list-total-active">{{ number_format((float)($activeReports->count())) }}</span>
        </div>
        <div class="sell-list-total-banner-row">
            <span class="sell-list-total-banner-label"><i class="fa fa-circle"></i> Added</span>
            <span class="sell-list-total-banner-value sell-list-total-added">{{ number_format((float)($addedReports->count())) }}</span>
        </div>
        <div class="sell-list-total-banner-row">
            <span class="sell-list-total-banner-label"><i class="fa fa-clock-o"></i> Date range</span>
            <span class="sell-list-total-banner-value sell-list-total-daterange">{{ $defaultDateFrom }} ~ {{ $defaultDateTo }}</span>
        </div>
    </div>
</div>

<div class="sell-list-tabs">
    <button type="button" class="sell-list-tab is-active" data-target="active">Active <span class="sell-list-tab-count sell-list-active-count">{{ $activeReports->count() }}</span></button>
    <button type="button" class="sell-list-tab" data-target="added">Added <span class="sell-list-tab-count sell-list-added-count">{{ $addedReports->count() }}</span></button>
</div>

<div class="sell-list-pane sell-list-pane-active is-active">
    @include('service::sale_pos.partials.hr_sell_list_rows', ['reports' => $activeReports, 'line_status' => 'active', 'show_copy_button' => true])
    @if ($activeReports->isEmpty())
        <div class="sell-list-empty tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-3 tw-px-3 tw-text-sky-800 tw-text-xs md:tw-text-sm">
            <i class="fa fa-info-circle"></i>
            <span>No active Sell Out records found.</span>
        </div>
    @endif
    <div class="sell-list-pane-loader tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-2 tw-px-3 tw-text-sky-700 tw-text-xs" style="display:none;">
        <i class="fa fa-spinner fa-spin"></i> <span>Loading more…</span>
    </div>
    <div class="sell-list-pane-end tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-2 tw-px-3 tw-text-slate-400 tw-text-xs" style="display:none;">
        <i class="fa fa-check-circle"></i> <span>End of list.</span>
    </div>
</div>

<div class="sell-list-pane sell-list-pane-added">
    @include('service::sale_pos.partials.hr_sell_list_rows', ['reports' => $addedReports, 'line_status' => 'added', 'show_copy_button' => false])
    @if ($addedReports->isEmpty())
        <div class="sell-list-empty tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-3 tw-px-3 tw-text-sky-800 tw-text-xs md:tw-text-sm">
            <i class="fa fa-info-circle"></i>
            <span>No added records yet.</span>
        </div>
    @endif
    <div class="sell-list-pane-loader tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-2 tw-px-3 tw-text-sky-700 tw-text-xs" style="display:none;">
        <i class="fa fa-spinner fa-spin"></i> <span>Loading more…</span>
    </div>
    <div class="sell-list-pane-end tw-w-full tw-flex tw-items-center tw-justify-center tw-gap-2 tw-py-2 tw-px-3 tw-text-slate-400 tw-text-xs" style="display:none;">
        <i class="fa fa-check-circle"></i> <span>End of list.</span>
    </div>
</div>

<input type="hidden" class="sell-list-page-active" value="{{ $page ?? 1 }}">
<input type="hidden" class="sell-list-page-added" value="{{ $page ?? 1 }}">
<input type="hidden" class="sell-list-has-more-active" value="{{ !empty($has_more) ? 1 : 0 }}">
<input type="hidden" class="sell-list-has-more-added" value="{{ !empty($has_more) ? 1 : 0 }}">
<input type="hidden" class="sell-list-per-page" value="{{ $per_page ?? 50 }}">
<input type="hidden" class="sell-list-total" value="{{ $total ?? 0 }}">
