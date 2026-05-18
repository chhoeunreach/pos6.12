@extends('layouts.app')

@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    if (! function_exists('loan_user_can')) {
        function loan_user_can($permission) {
            return LoanMenuHelper::loanUserCan((string) $permission);
        }
    }

    $moduleCssPath = base_path('Modules/LoanManagement/Resources/assets/css/loan-management.css');
    $moduleJsPath = base_path('Modules/LoanManagement/Resources/assets/js/loan-management.js');
    $loanBadgeCounts = LoanMenuHelper::badgeCounts();
@endphp

@section('title', trim($__env->yieldContent('title')) !== '' ? $__env->yieldContent('title') . ' - LoanManagement' : 'LoanManagement')

@section('css')
    @parent
    @if (file_exists($moduleCssPath))
        <style>{!! file_get_contents($moduleCssPath) !!}</style>
    @endif
    @yield('loan_css')
@endsection

@section('content')
<div class="lm-app" id="loanManagementApp">
    @include('loanmanagement::layouts.sidebar', ['loanBadgeCounts' => $loanBadgeCounts])

    <div class="lm-main" id="loanManagementMain">
        @include('loanmanagement::layouts.header')

        <main class="lm-content">
            @include('loanmanagement::layouts.breadcrumb')
            <div class="container-fluid lm-workspace">
                @yield('content_body')
            </div>
        </main>

        @include('loanmanagement::layouts.footer')
    </div>
</div>
@endsection

@section('javascript')
    @parent
    @if (file_exists($moduleJsPath))
        <script>{!! file_get_contents($moduleJsPath) !!}</script>
    @endif
    @yield('loan_js')
@endsection
