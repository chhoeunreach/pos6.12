<!DOCTYPE html>
<html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title')</title> 

    @stack('meta')

    <!-- Tailwind CSS (includes DaisyUI) -->
    <link href="{{ asset('modules/service/v7/css/tailwind/app.css?v='.$asset_v.'-service-jsfix-20260609') }}" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('modules/service/v7/css/vendor.css?v='.$asset_v.'-service-jsfix-20260609') }}">

    <!-- app css -->
    <link rel="stylesheet" href="{{ asset('modules/service/v7/css/app.css?v='.$asset_v.'-service-jsfix-20260609') }}">

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>
    <div id="app"></div>
    @if (session('status'))
        <input type="hidden" id="status_span" data-status="{{ session('status.success') }}" data-msg="{{ session('status.msg') }}">
    @endif
    @yield('content')

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js?v=$asset_v"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js?v=$asset_v"></script>
    <![endif]-->

    <!-- jQuery 2.2.3 -->
    @include('layouts.partials.disable_unload_listener')
    <script src="{{ asset('js/vendor.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
    <script src="{{ asset('js/functions.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
    @yield('javascript')
</body>

</html>
