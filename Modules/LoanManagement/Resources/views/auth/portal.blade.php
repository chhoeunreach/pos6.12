@php
    $settings = $settings ?? \Modules\LoanManagement\Services\BusinessSettingsService::get();
    $businessName = $settings['business_name'];
    $logo = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $background = \Modules\LoanManagement\Services\BusinessSettingsService::loginBackgroundUrl() ?: route('loan-management.public.home-image');
    $cms = \Modules\LoanManagement\Services\CmsHomeService::normalize($settings['home_cms'] ?? []);
    $isCustomer = $portal === 'customer';
    $loginField = $isCustomer ? 'login' : 'email';
    $loginAction = $isCustomer ? route('loan-management.public.customer-login.store') : route('login');
    $currentCustomer = Auth::guard('customer_loan')->user();
    $currentAdmin = Auth::guard('web')->user();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $businessName }} - {{ $isCustomer ? 'Customer' : 'Admin & Staff' }} Sign In</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing:border-box; letter-spacing:0; }
        body { margin:0; font:14px/1.5 Arial,sans-serif; color:#202020; background:#202020; min-height:100vh; display:flex; flex-direction:column; }
        .backdrop { position:fixed; inset:0; width:100%; height:100%; object-fit:cover; opacity:.24; pointer-events:none; }
        main { position:relative; flex:1; display:grid; place-items:center; padding:36px 16px; }
        .login-card { width:100%; max-width:440px; padding:30px; background:#fff; border:1px solid #e4e7ec; border-radius:8px; box-shadow:0 16px 48px #0003; }
        a { color:#b64d17; text-decoration:none; }
        a:hover { text-decoration:underline; }
        .brand-row { display:flex; justify-content:space-between; align-items:center; gap:12px; padding-bottom:20px; border-bottom:1px solid #e4e7ec; }
        .brand { display:flex; align-items:center; gap:10px; min-width:0; color:#202020; font-weight:800; font-size:15px; }
        .brand span { overflow-wrap:anywhere; }
        .logo { width:42px; height:40px; flex-shrink:0; display:grid; place-items:center; background:#e06b29; color:#fff; border-radius:4px; }
        .logo img { width:100%; height:100%; object-fit:contain; background:#fff; }
        .website { font-size:12px; white-space:nowrap; }
        .portals { display:flex; gap:4px; padding:4px; background:#f1f2f4; border-radius:6px; margin:22px 0; }
        .portals a { flex:1; text-align:center; padding:10px 6px; font-size:12px; font-weight:700; color:#667085; border-radius:4px; }
        .portals a.active { color:#202020; background:#fff; box-shadow:0 1px 4px #0001; }
        .portals i,.field-label i { color:#c4561c; margin-right:5px; }
        h1 { font-size:25px; line-height:1.25; margin:0 0 6px; overflow-wrap:anywhere; }
        .subtitle { margin:0 0 24px; color:#667085; font-size:13px; }
        .field { margin-bottom:18px; }
        .field-label { display:block; font-size:12px; font-weight:700; margin-bottom:7px; }
        input:not([type=checkbox]) { width:100%; border:1px solid #d5d9df; padding:12px 14px; height:46px; border-radius:6px; font:inherit; background:#fff; }
        input:focus { border-color:#e06b29; }
        :focus-visible { outline:3px solid #36a3bc; outline-offset:2px; }
        .password-row { position:relative; }
        .password-row input { padding-right:48px; }
        .password-toggle { position:absolute; right:4px; top:4px; height:38px; width:38px; background:transparent; border:0; color:#667085; cursor:pointer; border-radius:4px; }
        .options { display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:12px; margin:2px 0 20px; }
        .remember { display:flex; align-items:center; gap:7px; color:#667085; }
        input[type=checkbox] { accent-color:#e06b29; width:16px; height:16px; margin:0; }
        .submit { width:100%; min-height:46px; background:#e06b29; color:#fff; border:0; border-radius:6px; font-size:14px; font-weight:700; cursor:pointer; }
        .submit:hover { background:#bd5019; }
        .submit:disabled { opacity:.65; cursor:wait; }
        .notice { background:#fff1e8; border:1px solid #f3c7a9; border-radius:6px; padding:12px; margin:0 0 18px; font-size:13px; overflow-wrap:anywhere; }
        .error { background:#fff0f0; border-color:#efb4b4; color:#a22121; }
        .notice ul { margin:0; padding-left:18px; }
        .session-actions { display:flex; gap:14px; flex-wrap:wrap; margin-top:8px; }
        .session-actions form { margin:0; }
        .text-button { border:0; padding:0; background:none; color:#b64d17; cursor:pointer; font:inherit; }
        .explore { border-top:1px solid #e4e7ec; margin-top:24px; padding-top:20px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; font-size:12px; font-weight:700; }
        .demo { margin-top:24px; padding:16px; border:1px solid #f3c7a9; border-radius:6px; background:#fff5ed; font-size:12px; }
        .demo h2 { color:#b64d17; font-size:13px; margin:0 0 10px; }
        .demo p { overflow-wrap:anywhere; margin:8px 0; }
        .demo button { min-height:36px; width:100%; border:1px solid #c4561c; background:#fff; color:#b64d17; border-radius:4px; font-weight:700; cursor:pointer; }
        footer { position:relative; text-align:center; padding:0 16px 24px; color:#d7d7d7; font-size:12px; }
        footer p { margin:5px 0; white-space:pre-line; overflow-wrap:anywhere; }
        footer a { color:#ffc39e; }
        @media(max-width:380px) { .login-card { padding:22px 18px; } .brand { font-size:13px; } .website { font-size:11px; } h1 { font-size:23px; } .portals a { font-size:11px; } }
    </style>
</head>
<body>
    <img class="backdrop" src="{{ $background }}" alt="" aria-hidden="true">
    <main><section class="login-card" aria-labelledby="formTitle">
        <div class="brand-row">
            <a class="brand" href="{{ route('loan-management.public.home') }}"><span class="logo">@if($logo)<img src="{{ $logo }}" alt="">@else{{ strtoupper(mb_substr($businessName, 0, 3)) }}@endif</span><span>{{ $businessName }}</span></a>
            @if($settings['cms_enabled'])<a class="website" href="{{ route('loan-management.public.home') }}"><i class="fa-solid fa-globe" aria-hidden="true"></i> Website</a>@endif
        </div>
        <nav class="portals" aria-label="Sign-in portal">
            @if($settings['customer_login_enabled'])<a href="{{ route('loan-management.public.customer-login') }}" @class(['active' => $isCustomer]) @if($isCustomer) aria-current="page" @endif><i class="fa-solid fa-user" aria-hidden="true"></i> Customer Portal</a>@endif
            <a href="{{ route('login') }}" @class(['active' => !$isCustomer]) @if(!$isCustomer) aria-current="page" @endif><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Admin &amp; Staff</a>
        </nav>
        <h1 id="formTitle">{{ $isCustomer ? 'Customer Sign In' : 'Admin & Staff Sign In' }}</h1>
        <p class="subtitle">{{ $isCustomer ? 'Sign in to view your loans, installment payments, and profile.' : 'Authorized access for installment management and approvals.' }}</p>
        @if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(is_string(session('status')))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if($currentCustomer || $currentAdmin)
            <div class="notice">Signed in as {{ $currentCustomer ? $currentCustomer->name : ($currentAdmin->name ?? $currentAdmin->username) }}.
                <div class="session-actions"><a href="{{ $currentCustomer ? route('loan-management.public.customer-dashboard') : route('loan-management.dashboard') }}">Dashboard</a>
                <form method="POST" action="{{ $currentCustomer ? route('loan-management.public.customer-logout') : route('logout') }}">@csrf<input type="hidden" name="redirect" value="{{ $isCustomer ? route('loan-management.public.customer-login') : route('login') }}"><button class="text-button" type="submit">Sign out</button></form></div>
            </div>
        @endif
        <form method="POST" action="{{ $loginAction }}" id="portalLoginForm">
            @csrf
            <div class="field"><label class="field-label" for="usernameInput"><i class="fa-solid fa-user" aria-hidden="true"></i> {{ $isCustomer ? 'Phone, Email or Username' : 'Email or Username' }}</label>
                <input type="text" id="usernameInput" name="{{ $loginField }}" value="{{ old($loginField) }}" autocomplete="username" maxlength="255" required autofocus placeholder="{{ $isCustomer ? 'Phone, email or username' : 'Email or username' }}" @if($errors->has($loginField)) aria-invalid="true" @endif>
            </div>
            <div class="field"><label class="field-label" for="passwordInput"><i class="fa-solid fa-lock" aria-hidden="true"></i> Password</label><div class="password-row">
                <input type="password" id="passwordInput" name="password" autocomplete="current-password" required placeholder="Enter password">
                <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password" aria-pressed="false" title="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
            </div></div>
            <div class="options"><label class="remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in</label>
                @if(!$isCustomer && Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password?</a>@endif
            </div>
            <button class="submit" type="submit" id="submitBtn">Sign In</button>
        </form>
        @if(!empty($demoLogin))
            <section class="demo" aria-label="Demo account"><h2><i class="fa-solid fa-bolt" aria-hidden="true"></i> Demo {{ $isCustomer ? 'Customer' : 'Admin' }} Login</h2>
                <p>{{ $demoLogin['name'] }}</p><p>Login: <strong>{{ $demoLogin['login'] }}</strong><br>Password: <strong>{{ $demoLogin['password'] }}</strong></p>
                <button type="button" id="fillDemo" data-login="{{ $demoLogin['login'] }}" data-password="{{ $demoLogin['password'] }}">Fill Demo Credentials</button>
            </section>
        @endif
        <div class="explore">
            @if($isCustomer)<a href="{{ route('loan-management.public.register') }}"><i class="fa-solid fa-file-pen" aria-hidden="true"></i> Request installment</a>@elseif($settings['customer_login_enabled'])<a href="{{ route('loan-management.public.customer-login') }}">Customer Login</a>@endif
            @if($settings['cms_enabled'])<a href="{{ route('loan-management.public.home') }}">Browse Products</a>@endif
        </div>
    </section></main>
    <footer><p>&copy; {{ date('Y') }} {{ $businessName }}. All rights reserved.</p>
        @if($cms['contact'])
            @if($cms['contact_address'])<p>{{ $cms['contact_address'] }}</p>@endif
            @if($cms['contact_phone'])<p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $cms['contact_phone']) }}">{{ $cms['contact_phone'] }}</a></p>@endif
        @endif
    </footer>
    <script>
        (function () {
            var password = document.getElementById('passwordInput');
            var toggle = document.getElementById('togglePassword');
            toggle.addEventListener('click', function () {
                var show = password.type === 'password';
                password.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(show));
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                toggle.title = show ? 'Hide password' : 'Show password';
                toggle.firstElementChild.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            });
            var submit = document.getElementById('submitBtn');
            var demo = document.getElementById('fillDemo');
            if (demo) demo.addEventListener('click', function () {
                document.getElementById('usernameInput').value = demo.dataset.login;
                password.value = demo.dataset.password;
                submit.focus();
            });
            document.getElementById('portalLoginForm').addEventListener('submit', function () { submit.disabled = true; submit.textContent = 'Signing in...'; });
            window.addEventListener('pageshow', function () { submit.disabled = false; submit.textContent = 'Sign In'; });
        })();
    </script>
</body>
</html>
