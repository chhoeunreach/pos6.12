@php
    $workspaceTabs = \Modules\LoanManagement\Helpers\LoanMenuHelper::workspaceTabs($workspace);
    $tabFilters = request()->only(['date_from', 'date_to', 'location_id', 'search', 'collector_id', 'business_location_id']);
@endphp
@if(count($workspaceTabs) > 1)
    <nav class="lm-workspace-tabs no-print" aria-label="{{ $workspaceLabel ?? __('Views') }}">
        @foreach($workspaceTabs as $tab)
            @php $tabActive = \Modules\LoanManagement\Helpers\LoanMenuHelper::navigationItemActive($tab); @endphp
            <a href="{{ route($tab['route'], array_merge($tabFilters, $tab['params'] ?? [])) }}"
               class="{{ $tabActive ? 'active' : '' }}" @if($tabActive) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
@endif
