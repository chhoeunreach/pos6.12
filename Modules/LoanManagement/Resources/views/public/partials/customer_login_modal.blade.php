@if($customerPortalEnabled && !$customerUser && !$adminUser)
@php
    $modalLang = strtolower((string) request()->query('lang', request()->query('language', session('user.language', request()->cookie('lm_lang', app()->getLocale() ?? 'en')))));
    $mIsKm = in_array($modalLang, ['km', 'kh', 'khmer']);
    $mText = function ($en, $km) use ($mIsKm) { return $mIsKm ? $km : $en; };
@endphp
<dialog class="installment-modal" id="customerLoginModal" aria-labelledby="customerLoginTitle">
    <button class="installment-close" type="button" id="customerLoginClose" aria-label="{{ $mText('Close login', 'បិទ') }}" title="{{ $mText('Close', 'បិទ') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    <h2 id="customerLoginTitle">{{ $mText('Customer Login', 'ចូលគណនីអតិថិជន') }}</h2>
    <p>{{ $mText('Access your loan records and installment payment history.', 'ពិនិត្យមើលព័ត៌មានកម្ចី និងប្រវត្តិនៃការបង់ប្រាក់រំលស់របស់អ្នក។') }}</p>
    <div class="installment-message" id="customerLoginError" role="alert" hidden></div>
    <form method="POST" action="{{ route('loan-management.public.customer-login.store') }}" id="customerLoginForm">
        @csrf
        <div class="installment-grid">
            <div class="installment-field-full"><label for="modalCustomerLogin">{{ $mText('Phone, Email or Username', 'លេខទូរស័ព្ទ អ៊ីមែល ឬឈ្មោះគណនី') }}</label><input id="modalCustomerLogin" name="login" autocomplete="username" maxlength="255" required autofocus></div>
            <div class="installment-field-full"><label for="modalCustomerPassword">{{ $mText('Password', 'ពាក្យសម្ងាត់') }}</label><input id="modalCustomerPassword" name="password" type="password" autocomplete="current-password" required></div>
        </div>
        <button class="installment-submit" type="submit" id="customerLoginSubmit">{{ $mText('Login to Portal', 'ចូលទៅកាន់ផតថល') }}</button>
    </form>
    <p style="margin-top:16px;"><a href="{{ route('loan-management.public.customer-login') }}" data-full-login>{{ $mText('Open Customer Portal', 'បើកផតថលអតិថិជន') }}</a></p>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('customerLoginModal');
    var form = document.getElementById('customerLoginForm');
    var errorBox = document.getElementById('customerLoginError');
    var button = document.getElementById('customerLoginSubmit');
    var pending = false;
    var isKmModal = {{ $mIsKm ? 'true' : 'false' }};
    var loginPath = new URL(@json(route('loan-management.public.customer-login')), location.href).pathname;
    var previousFocus;
    var previousOverflow;
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('data-full-login') || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        var url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.pathname !== loginPath) return;
        event.preventDefault();
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        errorBox.hidden = true;
        dialog.showModal();
    });
    document.getElementById('customerLoginClose').addEventListener('click', function () { if (!pending) dialog.close(); });
    dialog.addEventListener('cancel', function (event) { if (pending) event.preventDefault(); });
    dialog.addEventListener('close', function () {
        document.body.style.overflow = previousOverflow || '';
        document.getElementById('modalCustomerPassword').value = '';
        if (previousFocus?.isConnected) previousFocus.focus();
    });
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (pending) return;
        pending = true;
        button.disabled = true;
        button.textContent = isKmModal ? 'កំពុងចូល...' : 'Signing in...';
        errorBox.hidden = true;
        try {
            var response = await fetch(form.action, {method:'POST', credentials:'same-origin', body:new FormData(form), headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest'}});
            var payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
            if (!response.ok || !payload.redirect) {
                throw new Error(Object.values(payload.errors || {}).flat()[0] || (response.status === 429 ? (isKmModal ? 'សូមរង់ចាំមួយនាទី មុននឹងព្យាយាមម្តងទៀត។' : 'Please wait a minute before trying again.') : response.status === 419 ? (isKmModal ? 'សូមផ្ទុកទំព័រឡើងវិញដើម្បីបន្តសម័យការងាររបស់អ្នក។' : 'Refresh the page to renew your session.') : response.status < 500 && payload.message ? payload.message : (isKmModal ? 'មិនអាចចូលបានទេ។ សូមព្យាយាមម្តងទៀត។' : 'Unable to sign in. Please try again.')));
            }
            location.assign(payload.redirect);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
            document.getElementById('modalCustomerPassword').value = '';
        } finally {
            pending = false;
            button.disabled = false;
            button.textContent = isKmModal ? 'ចូលទៅកាន់ផតថល' : 'Login to Portal';
        }
    });
})();
</script>
@endif
