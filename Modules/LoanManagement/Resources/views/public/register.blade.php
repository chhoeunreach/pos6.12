@php
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $businessName = \Modules\LoanManagement\Services\BusinessSettingsService::businessName();
    $themeColor = $settings['theme_color'] ?? '#2563eb';
    $customerUser = Auth::guard('customer_loan')->user();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - {{ $businessName }}</title>
    <style>
        :root { --public-primary: {{ $themeColor }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f7fb; color: #102033; }
        .page { min-height: 100vh; display: grid; place-items: center; padding: 28px 16px; }
        .panel { width: min(760px, 100%); background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 18px 54px rgba(15,23,42,.10); overflow: hidden; }
        .head { padding: 22px 24px; display: flex; justify-content: space-between; gap: 14px; align-items: center; border-bottom: 1px solid #e8eef6; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: #0f172a; font-weight: 800; text-decoration: none; }
        .logo { width: 42px; height: 42px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; background: #edf3fb; color: var(--public-primary); }
        .logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .link { color: var(--public-primary); font-weight: 800; text-decoration: none; }
        .body { padding: 24px; }
        h1 { margin: 0 0 6px; font-size: 28px; letter-spacing: 0; }
        .muted { margin: 0 0 22px; color: #64748b; line-height: 1.6; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .field-full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 7px; color: #334155; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        input, textarea { width: 100%; border: 1px solid #dbe4ef; border-radius: 6px; padding: 12px; font: inherit; color: #0f172a; outline: none; }
        textarea { min-height: 88px; resize: vertical; }
        input:focus, textarea:focus { border-color: var(--public-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .errors { margin: 0 0 18px; padding: 12px 14px; background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; border-radius: 6px; }
        .cart-box { margin: 0 0 20px; padding: 14px; border: 1px solid #dbeafe; background: #eff6ff; border-radius: 8px; display: none; }
        .cart-box h2 { margin: 0 0 10px; font-size: 16px; letter-spacing: 0; color: #0f172a; }
        .cart-list { display: grid; gap: 8px; }
        .cart-row { display: flex; justify-content: space-between; gap: 12px; color: #334155; font-size: 13px; }
        .cart-total { margin-top: 10px; padding-top: 10px; border-top: 1px solid #bfdbfe; display: flex; justify-content: space-between; gap: 12px; font-weight: 800; color: #0f172a; }
        .actions { margin-top: 22px; display: flex; align-items: center; justify-content: flex-end; gap: 12px; }
        .button { min-height: 44px; border: 0; border-radius: 6px; background: var(--public-primary); color: #fff; padding: 0 18px; font-weight: 800; cursor: pointer; }
        @media (max-width: 680px) { .grid { grid-template-columns: 1fr; } .head, .actions { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="page">
        <section class="panel">
            <header class="head">
                <a class="brand" href="{{ route('loan-management.public.home') }}">
                    <span class="logo">@if($businessLogoUrl)<img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">@else{{ strtoupper(mb_substr($businessName, 0, 1)) }}@endif</span>
                    <span>{{ $businessName }}</span>
                </a>
                <a class="link" href="{{ route('loan-management.public.customer-login') }}" @if($customerUser) onclick="return confirm('You are currently logged in as Customer ({{ $customerUser->name }}). Are you sure you want to log out first to switch or sign in to another account?');" @endif>Already registered?</a>
            </header>
            <div class="body">
                <h1>Customer Registration</h1>
                <p class="muted">Create your customer account. Our team can review your information and contact you for the next step.</p>

                @if($customerUser)
                    <div style="margin-bottom: 18px; padding: 12px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <strong style="color: #1e40af; display: block; font-size: 14px;">👤 You are currently logged in as {{ $customerUser->name }}</strong>
                            <span style="color: #3b82f6; font-size: 12px;">You can go to your dashboard, or submit this form to register an additional customer account.</span>
                        </div>
                        <a href="{{ route('loan-management.public.customer-dashboard') }}" style="display: inline-flex; align-items: center; padding: 6px 12px; background: #2563eb; color: #fff; border-radius: 6px; font-weight: 800; font-size: 12px; text-decoration: none;">Go to Dashboard</a>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="errors">{{ $errors->first() }}</div>
                @endif

                <div class="cart-box" id="installmentCartBox">
                    <h2>Selected products for installment</h2>
                    <div class="cart-list" id="installmentCartList"></div>
                    <div class="cart-total"><span>Total</span><span id="installmentCartTotal">$0.00</span></div>
                </div>

                <form method="POST" action="{{ route('loan-management.public.register.store') }}">
                    @csrf
                    <input type="hidden" name="installment_items" id="installmentItemsInput" value="{{ old('installment_items') }}">
                    <div class="grid">
                        <div>
                            <label for="name">English Name</label>
                            <input id="name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div>
                            <label for="khmer_name">Khmer Name</label>
                            <input id="khmer_name" name="khmer_name" value="{{ old('khmer_name') }}">
                        </div>
                        <div>
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" value="{{ old('phone') }}" required>
                        </div>
                        <div>
                            <label for="telegram">Telegram</label>
                            <input id="telegram" name="telegram" value="{{ old('telegram') }}">
                        </div>
                        <div class="field-full">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}">
                        </div>
                        <div class="field-full">
                            <label for="address">Address</label>
                            <textarea id="address" name="address">{{ old('address') }}</textarea>
                        </div>
                        <div>
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" required minlength="8">
                        </div>
                        <div>
                            <label for="password_confirmation">Confirm Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8">
                        </div>
                    </div>
                    <div class="actions">
                        <a class="link" href="{{ route('loan-management.public.home') }}">Back home</a>
                        <button class="button" type="submit">Create Account</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
    <script>
        (function () {
            var cartKey = 'loan_public_installment_cart';
            var input = document.getElementById('installmentItemsInput');
            var box = document.getElementById('installmentCartBox');
            var list = document.getElementById('installmentCartList');
            var totalBox = document.getElementById('installmentCartTotal');

            function money(value) {
                return '$' + Number(value || 0).toFixed(2);
            }

            function loadCart() {
                try {
                    return JSON.parse(localStorage.getItem(cartKey) || '[]') || [];
                } catch (e) {
                    return [];
                }
            }

            var cart = loadCart();
            if (!cart.length && input.value) {
                try {
                    cart = JSON.parse(input.value) || [];
                } catch (e) {
                    cart = [];
                }
            }

            if (cart.length) {
                var total = 0;
                box.style.display = 'block';
                list.innerHTML = '';
                cart.forEach(function (item) {
                    var qty = Number(item.qty || 1);
                    var price = Number(item.price || 0);
                    total += qty * price;
                    var row = document.createElement('div');
                    row.className = 'cart-row';
                    row.innerHTML = '<span></span><strong></strong>';
                    row.querySelector('span').textContent = (item.name || 'Product') + ' x' + qty;
                    row.querySelector('strong').textContent = money(qty * price);
                    list.appendChild(row);
                });
                totalBox.textContent = money(total);
                input.value = JSON.stringify(cart);
            }
        })();
    </script>
</body>
</html>
