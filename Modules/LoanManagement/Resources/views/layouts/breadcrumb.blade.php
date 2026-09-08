@php
    $currentTitle = trim($__env->yieldContent('title'));
    $businessSettings = \Modules\LoanManagement\Services\BusinessSettingsService::get();
@endphp

<div class="lm-breadcrumb-wrap">
    <ol class="breadcrumb lm-breadcrumb">
        <li><a href="{{ route('loan-management.dashboard') }}">{{ $businessSettings['system_name'] }}</a></li>
        @if($currentTitle !== '')
            <li class="active">{{ $currentTitle }}</li>
        @endif
    </ol>
</div>
