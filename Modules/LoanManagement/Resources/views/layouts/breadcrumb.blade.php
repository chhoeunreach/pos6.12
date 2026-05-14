@php
    $currentTitle = trim($__env->yieldContent('title'));
@endphp

<div class="lm-breadcrumb-wrap">
    <ol class="breadcrumb lm-breadcrumb">
        <li><a href="{{ route('loan-management.dashboard') }}">LoanManagement</a></li>
        @if($currentTitle !== '')
            <li class="active">{{ $currentTitle }}</li>
        @endif
    </ol>
</div>
