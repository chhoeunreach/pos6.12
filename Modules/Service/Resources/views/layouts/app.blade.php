@inject('request', 'Illuminate\Http\Request')

@php
    $routePrefix = trim(config('service.route_prefix', 'service-pos'), '/');
    $isServiceRoute = $request->segment(1) == $routePrefix;
    $firstSegment = $isServiceRoute ? $request->segment(2) : $request->segment(1);
    $secondSegment = $isServiceRoute ? $request->segment(3) : $request->segment(2);
    $thirdSegment = $isServiceRoute ? $request->segment(4) : $request->segment(3);
    $isCustomerDisplay = $firstSegment == 'customer-display';
@endphp

@if (
    $firstSegment == 'pos' &&
        ($secondSegment == 'create' || $thirdSegment == 'edit' || $secondSegment == 'payment'))
    @php
        $pos_layout = true;
    @endphp
@else
    @php
        $pos_layout = false;
    @endphp
@endif

@php
    $whitelist = ['127.0.0.1', '::1'];
@endphp

<!DOCTYPE html>
<html class="tw-bg-white tw-scroll-smooth" lang="{{ app()->getLocale() }}"
    dir="{{ in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr' }}">
<head>
    <!-- Tell the browser to be responsive to screen width -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"
        name="viewport">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title') - {{ Session::get('business.name') }}</title>

    @include('service::layouts.partials.css')
    

    @include('service::layouts.partials.extracss')

    @yield('css')

</head>
<body
    class="tw-font-sans tw-antialiased tw-text-gray-900 tw-bg-gray-100 @if ($pos_layout) hold-transition lockscreen @else hold-transition skin-@if (!empty(session('business.theme_color'))){{ session('business.theme_color') }}@else{{ 'blue-light' }} @endif sidebar-mini @endif" >
    <div class="tw-flex thetop">
        <script type="text/javascript">
            if (localStorage.getItem("upos_sidebar_collapse") == 'true') {
                var body = document.getElementsByTagName("body")[0];
                body.className += " sidebar-collapse";
            }
        </script>
        @if (!$pos_layout && !$isCustomerDisplay)
            @include('service::layouts.partials.sidebar')
        @endif

        @if (in_array($_SERVER['REMOTE_ADDR'], $whitelist))
            <input type="hidden" id="__is_localhost" value="true">
        @endif

        <!-- Add currency related field-->
        <input type="hidden" id="__code" value="{{ session('currency')['code'] }}">
        <input type="hidden" id="__symbol" value="{{ session('currency')['symbol'] }}">
        <input type="hidden" id="__thousand" value="{{ session('currency')['thousand_separator'] }}">
        <input type="hidden" id="__decimal" value="{{ session('currency')['decimal_separator'] }}">
        <input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement') }}">
        <input type="hidden" id="__precision" value="{{ session('business.currency_precision', 2) }}">
        <input type="hidden" id="__quantity_precision" value="{{ session('business.quantity_precision', 2) }}">
        <!-- End of currency related field-->
        @can('view_export_buttons')
            <input type="hidden" id="view_export_buttons">
        @endcan
        @if (isMobile())
            <input type="hidden" id="__is_mobile">
        @endif
        @if (session('status'))
            <input type="hidden" id="status_span" data-status="{{ session('status.success') }}"
                data-msg="{{ session('status.msg') }}">
        @endif
        <main class="tw-flex tw-flex-col tw-flex-1 tw-h-full tw-min-w-0 tw-bg-gray-100">
            @if(!$isCustomerDisplay && !$pos_layout)
                @include('service::layouts.partials.header')
            @elseif(!$isCustomerDisplay)
                @include('service::layouts.partials.header-pos')
            @endif
            <!-- empty div for vuejs -->
            <div id="app">
                @yield('vue')
            </div>
            <div class="tw-flex-1 tw-overflow-y-auto tw-h-screen" id="scrollable-container">
                @yield('content')
                @if (!$pos_layout)
                
                    @include('service::layouts.partials.footer')
                @else
                    @include('service::layouts.partials.footer_pos')
                @endif
            </div>
            <div class='scrolltop no-print'>
                <div class='scroll icon'><i class="fas fa-angle-up"></i></div>
            </div>

            @if (config('constants.iraqi_selling_price_adjustment'))
                <input type="hidden" id="iraqi_selling_price_adjustment">
            @endif

            <!-- This will be printed -->
            <section class="invoice print_section" id="receipt_section">
            </section>
        </main>

        @include('service::home.todays_profit_modal')
        <!-- /.content-wrapper -->



        <audio id="success-audio">
            <source src="{{ asset('/audio/success.ogg?v=' . $asset_v . '-service-jsfix-20260609') }}" type="audio/ogg">
            <source src="{{ asset('/audio/success.mp3?v=' . $asset_v . '-service-jsfix-20260609') }}" type="audio/mpeg">
        </audio>
        <audio id="error-audio">
            <source src="{{ asset('/audio/error.ogg?v=' . $asset_v . '-service-jsfix-20260609') }}" type="audio/ogg">
            <source src="{{ asset('/audio/error.mp3?v=' . $asset_v . '-service-jsfix-20260609') }}" type="audio/mpeg">
        </audio>
        <audio id="warning-audio">
            <source src="{{ asset('/audio/warning.ogg?v=' . $asset_v . '-service-jsfix-20260609') }}" type="audio/ogg">
            <source src="{{ asset('/audio/warning.mp3?v=' . $asset_v . '-service-jsfix-20260609') }}" type="audio/mpeg">
        </audio>

        @if (!empty($__additional_html))
            {!! $__additional_html !!}
        @endif

        @include('service::layouts.partials.javascripts')
        
        {{-- Module JS --}}
        @include('service::layouts.module-assets')
        <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

        @if (!empty($__additional_views) && is_array($__additional_views))
            @foreach ($__additional_views as $additional_view)
                @includeIf($additional_view)
            @endforeach
        @endif
        <div>

            <div class="overlay tw-hidden"></div>
        </div>
</body>
<style>
    @media print {
        #scrollable-container {
            overflow: visible !important;
            height: auto !important;
        }
        
        /* Hide side menu */
        .side-bar,
        .thetop > aside {
            display: none !important;
        }
    }
</style>
<style>
    .small-view-side-active {
        display: flex !important;
        flex-direction: column;
        z-index: 1000;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
    }
    .overlay {
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.8);
        position: fixed;
        top: 0;
        left: 0;
        display: none;
        z-index: 20;
    }

    .tw-dw-btn.tw-dw-btn-xs.tw-dw-btn-outline {
        width: max-content;
        margin: 2px;
    }

    #scrollable-container{
        position:relative;
    }
    



</style>

</html>
