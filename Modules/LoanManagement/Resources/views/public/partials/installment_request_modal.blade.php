@php
    $modalLang = strtolower((string) request()->query('lang', request()->query('language', session('user.language', request()->cookie('lm_lang', app()->getLocale() ?? 'en')))));
    $mIsKm = in_array($modalLang, ['km', 'kh', 'khmer']);
    $mText = function ($en, $km) use ($mIsKm) { return $mIsKm ? $km : $en; };
@endphp
<style>
    .installment-modal { width:min(720px, calc(100% - 32px)); max-height:calc(100dvh - 32px); padding:32px; border:0; border-radius:8px; background:#fff; color:#202020; overflow-y:auto; }
    .installment-modal::backdrop { background:rgba(0,0,0,.60); }
    .installment-modal h2 { margin:0 36px 10px 0; font-size:28px; line-height:1.25; }
    .installment-modal p { color:#667085; margin:0 0 24px; line-height:1.6; }
    .installment-close { position:absolute; top:16px; right:16px; width:32px; height:32px; padding:0; border:0; background:transparent; color:#667085; font-size:20px; }
    .installment-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:20px; }
    .installment-field-full { grid-column:1 / -1; }
    .installment-docs { margin-top:20px; padding-top:20px; border-top:1px solid #e4e7ec; }
    .installment-docs-title { font-size:14px; font-weight:700; margin:0 0 4px; }
    .installment-docs-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; margin-top:14px; }
    .installment-docs-note { font-size:12px; color:#667085; margin:12px 0 0; }
    .installment-modal label { display:block; margin-bottom:8px; font-size:14px; font-weight:600; }
    .installment-modal input, .installment-modal textarea { width:100%; min-width:0; padding:12px 14px; border:1px solid #dbe0e7; border-radius:6px; background:#fff; color:#202020; font:inherit; }
    .installment-modal textarea { resize:vertical; min-height:72px; }
    .installment-modal textarea[readonly] { background:#f8f9fb; }
    .installment-submit { width:100%; min-height:48px; margin-top:24px; border:0; border-radius:6px; background:var(--public-primary, #b9570b); color:#fff; font:inherit; font-weight:700; }
    .installment-submit:disabled { cursor:wait; opacity:.65; }
    .installment-message { padding:12px 14px; margin-bottom:20px; border-radius:6px; background:#fff1f2; color:#b42318; overflow-wrap:anywhere; }
    .installment-success .installment-message { background:#edf9f1; color:#18733d; }
    .installment-success h2 { margin-top:12px; }
    .installment-modal [hidden] { display:none !important; }
    @media (max-width:540px) { .installment-modal { padding:24px 20px; } .installment-modal h2 { font-size:23px; } .installment-grid { grid-template-columns:1fr; gap:16px; } .installment-docs-grid { grid-template-columns:1fr; } }
</style>
<dialog class="installment-modal" id="installmentRequestModal" aria-labelledby="installmentRequestTitle">
    <button class="installment-close" type="button" id="installmentRequestClose" aria-label="{{ $mText('Close request', 'បិទ') }}" title="{{ $mText('Close', 'បិទ') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    <div id="installmentRequestFields">
        <h2 id="installmentRequestTitle">{{ $mText('Register Installment Request', 'ចុះឈ្មោះស្នើសុំបង់រំលស់') }}</h2>
        <p>{{ $mText('Send your contact details and selected products. Our staff will follow up on your request.', 'ផ្ញើព័ត៌មានទំនាក់ទំនង និងទំនិញដែលបានជ្រើសរើស។ បុគ្គលិករបស់យើងនឹងទាក់ទងមកអ្នកវិញ។') }}</p>
        <div class="installment-message" id="installmentRequestError" role="alert" hidden></div>
        <form method="POST" action="{{ route('loan-management.public.register.store') }}" id="installmentRequestForm">
            @csrf
            <input type="hidden" name="installment_request" value="1">
            <input type="hidden" name="preferred_months" id="requestPreferredMonths">
            <input type="hidden" name="preferred_down_payment" id="requestPreferredDownPayment">
            <input type="hidden" name="installment_items" id="requestInstallmentItems" value="{{ old('installment_items', '[]') }}">
            <div class="installment-grid">
                <div>
                    <label for="requestFullName">{{ $mText('Full Name', 'ឈ្មោះពេញ') }}</label>
                    <input id="requestFullName" name="name" maxlength="255" autocomplete="name" placeholder="{{ $mText('Your name', 'ឈ្មោះរបស់អ្នក') }}" value="{{ old('name') }}" required autofocus>
                </div>
                <div>
                    <label for="requestPhone">{{ $mText('Phone Number', 'លេខទូរស័ព្ទ') }}</label>
                    <input id="requestPhone" name="phone" type="tel" maxlength="50" autocomplete="tel" placeholder="+855 ..." value="{{ old('phone') }}" required>
                </div>
                <div class="installment-field-full">
                    <label for="requestAddress">{{ $mText('Delivery / Contact Address', 'អាសយដ្ឋានដឹកជញ្ជូន / ទំនាក់ទំនង') }}</label>
                    <textarea id="requestAddress" name="address" maxlength="1000" autocomplete="street-address" placeholder="{{ $mText('Phnom Penh address', 'អាសយដ្ឋាន រាជធានីភ្នំពេញ ឬខេត្ត...') }}" required>{{ old('address') }}</textarea>
                </div>
                <div class="installment-field-full">
                    <label for="requestSelectedProducts">{{ $mText('Selected Products', 'ទំនិញដែលបានជ្រើសរើស') }}</label>
                    <textarea id="requestSelectedProducts" readonly rows="2"></textarea>
                </div>
            </div>
            <div class="installment-docs">
                <p class="installment-docs-title">{{ $mText('Documents (optional)', 'ឯកសារភ្ជាប់ (ស្រេចចិត្ត)') }}</p>
                <p class="installment-docs-note">{{ $mText('Add multiple photos or PDFs. Crop a photo if you want to trim it, or keep the original file exactly as it is.', 'ភ្ជាប់រូបថត ឬឯកសារ PDF។ អ្នកអាចកាត់តម្រឹមរូបថត ឬរក្សាទុកឯកសារដើម។') }}</p>
                <div class="installment-docs-grid">
                    <div class="md-doc-slot" data-md-slot data-md-max="8">
                        <input type="file" name="id_card_front[]" multiple accept="image/*,application/pdf" data-md-input>
                        <span class="md-doc-count" data-md-count>0</span>
                        <div class="doc-label" style="font-size:12px; font-weight:800; color:#202020;">{{ $mText('National ID (Front)', 'អត្តសញ្ញាណប័ណ្ណ (ខាងមុខ)') }}</div>
                        <div class="md-doc-hint">{{ $mText('Up to 8 files', 'រហូតដល់ ៨ ឯកសារ') }}</div>
                        <div data-md-queue></div>
                        <button type="button" class="md-doc-add" data-md-add>{{ $mText('+ Add more files', '+ បន្ថែមឯកសារ') }}</button>
                    </div>
                    <div class="md-doc-slot" data-md-slot data-md-max="8">
                        <input type="file" name="id_card_back[]" multiple accept="image/*,application/pdf" data-md-input>
                        <span class="md-doc-count" data-md-count>0</span>
                        <div class="doc-label" style="font-size:12px; font-weight:800; color:#202020;">{{ $mText('National ID (Back)', 'អត្តសញ្ញាណប័ណ្ណ (ខាងក្រោយ)') }}</div>
                        <div class="md-doc-hint">{{ $mText('Up to 8 files', 'រហូតដល់ ៨ ឯកសារ') }}</div>
                        <div data-md-queue></div>
                        <button type="button" class="md-doc-add" data-md-add>{{ $mText('+ Add more files', '+ បន្ថែមឯកសារ') }}</button>
                    </div>
                    <div class="md-doc-slot" data-md-slot data-md-max="8">
                        <input type="file" name="income_proof[]" multiple accept="image/*,application/pdf" data-md-input>
                        <span class="md-doc-count" data-md-count>0</span>
                        <div class="doc-label" style="font-size:12px; font-weight:800; color:#202020;">{{ $mText('Income Proof / Pay Slip', 'លិខិតបញ្ជាក់ប្រាក់ចំណូល / ប័ណ្ណបើកប្រាក់ខែ') }}</div>
                        <div class="md-doc-hint">{{ $mText('Up to 8 files', 'រហូតដល់ ៨ ឯកសារ') }}</div>
                        <div data-md-queue></div>
                        <button type="button" class="md-doc-add" data-md-add>{{ $mText('+ Add more files', '+ បន្ថែមឯកសារ') }}</button>
                    </div>
                    <div class="md-doc-slot" data-md-slot data-md-max="8">
                        <input type="file" name="collateral_photo[]" multiple accept="image/*,application/pdf" data-md-input>
                        <span class="md-doc-count" data-md-count>0</span>
                        <div class="doc-label" style="font-size:12px; font-weight:800; color:#202020;">{{ $mText('Product / Receipt Photo', 'រូបថតទំនិញ / វិក្កយបត្រ') }}</div>
                        <div class="md-doc-hint">{{ $mText('Optional - up to 8 files', 'ស្រេចចិត្ត - រហូតដល់ ៨ ឯកសារ') }}</div>
                        <div data-md-queue></div>
                        <button type="button" class="md-doc-add" data-md-add>{{ $mText('+ Add more files', '+ បន្ថែមឯកសារ') }}</button>
                    </div>
                </div>
            </div>
            <button class="installment-submit" type="submit" id="installmentRequestSubmit">{{ $mText('Submit Registration & Cart', 'ដាក់ស្នើការចុះឈ្មោះ និងកន្ត្រក') }}</button>
        </form>
    </div>
    <div class="installment-success" id="installmentRequestSuccess" hidden tabindex="-1" role="status">
        <h2 id="installmentRequestSuccessTitle">{{ $mText('Request Submitted', 'សំណើត្រូវបានដាក់រួចរាល់') }}</h2>
        <div class="installment-message"><i class="fa-solid fa-clock" aria-hidden="true"></i> {{ $mText('Pending Staff Follow-Up', 'រង់ចាំបុគ្គលិកទាក់ទងមកវិញ') }}</div>
        <p id="installmentRequestSuccessMessage"></p>
        <button class="installment-submit" type="button" id="installmentRequestDone">{{ $mText('Done', 'រួចរាល់') }}</button>
    </div>
</dialog>
@if(session('installment_request_success'))
    <div class="installment-message" role="status">{{ session('installment_request_success') }}</div>
@endif
@include('loanmanagement::partials.multi_doc_upload')
<script>
(function () {
    var dialog = document.getElementById('installmentRequestModal');
    var form = document.getElementById('installmentRequestForm');
    var fields = document.getElementById('installmentRequestFields');
    var success = document.getElementById('installmentRequestSuccess');
    var errorBox = document.getElementById('installmentRequestError');
    var submit = document.getElementById('installmentRequestSubmit');
    var usesCart = true;
    var isKmModal = {{ $mIsKm ? 'true' : 'false' }};
    var previousFocus;
    var previousOverflow;
    var submitting = false;
    var oldError = @json(isset($errors) && $errors->any() ? $errors->first() : '');

    function docSlots() {
        return window.MDUpload ? window.MDUpload.slots.filter(function (slot) { return form.contains(slot.el); }) : [];
    }
    function clearDocs() {
        docSlots().forEach(function (slot) { slot.clear(); });
    }

    function closeRequest() {
        if (!submitting) dialog.close();
    }
    window.openInstallmentRequest = function (items, clearCart, preferences) {
        previousFocus = document.activeElement;
        usesCart = clearCart;
        clearDocs();
        fields.hidden = false;
        success.hidden = true;
        dialog.setAttribute('aria-labelledby', 'installmentRequestTitle');
        errorBox.textContent = oldError;
        errorBox.hidden = !oldError;
        oldError = '';
        document.getElementById('requestPreferredMonths').value = preferences?.months || '';
        document.getElementById('requestPreferredDownPayment').value = preferences?.downPayment ?? '';
        items = Array.isArray(items) ? items : [];
        document.getElementById('requestInstallmentItems').value = JSON.stringify(items.map(function (item) {
            return {id:item.id, variation_id:item.variation_id || 0, qty:item.qty || 1};
        }));
        document.getElementById('requestSelectedProducts').value = items.length ? items.map(function (item) {
            return (item.name || 'Product') + ' x' + Number(item.qty || 1) + ' ($' + (Number(item.price || 0) * Number(item.qty || 1)).toFixed(2) + ')';
        }).join('\n') : (isKmModal ? 'បុគ្គលិកនឹងជួយលោកអ្នកជ្រើសរើសទំនិញ។' : 'Staff will help you choose a product.');
        if (preferences?.months) {
            document.getElementById('requestSelectedProducts').value += isKmModal
                ? ('\nរយៈពេលបង់រំលស់: ' + preferences.months + ' ខែ; ប្រាក់កក់មុន: $' + Number(preferences.downPayment || 0).toFixed(2))
                : ('\nPreferred term: ' + preferences.months + ' months; down payment: $' + Number(preferences.downPayment || 0).toFixed(2));
        }
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialog.showModal();
    };
    document.getElementById('installmentRequestClose').addEventListener('click', closeRequest);
    document.getElementById('installmentRequestDone').addEventListener('click', closeRequest);
    dialog.addEventListener('cancel', function (event) { if (submitting) event.preventDefault(); });
    dialog.addEventListener('click', function (event) {
        var bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) closeRequest();
    });
    dialog.addEventListener('close', function () {
        document.body.style.overflow = previousOverflow || '';
        if (previousFocus && previousFocus.isConnected) previousFocus.focus();
    });
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (submitting) return;
        submitting = true;
        submit.disabled = true;
        submit.textContent = isKmModal ? 'កំពុងដាក់ស្នើ...' : 'Submitting...';
        errorBox.hidden = true;
        try {
            window.MDUpload.syncAll();
            var response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', body: new FormData(form),
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            var payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
            if (!response.ok || payload.status !== 'pending') {
                var errors = Object.values(payload.errors || {}).flat();
                throw new Error(errors[0] || (response.status === 419 ? (isKmModal ? 'សម័យការងាររបស់អ្នកបានផុតកំណត់។ សូមផ្ទុកទំព័រឡើងវិញ។' : 'Your session has expired. Refresh the page and try again.') : response.status === 429 ? (isKmModal ? 'សូមរង់ចាំមួយនាទី មុននឹងដាក់ស្នើឡើងវិញ។' : 'Please wait a minute before submitting another request.') : response.status < 500 && payload.message ? payload.message : (isKmModal ? 'មិនអាចដាក់ស្នើសំណើរបស់អ្នកបានទេ។ សូមព្យាយាមម្តងទៀត។' : 'Unable to submit your request. Please try again.')));
            }
            fields.hidden = true;
            success.hidden = false;
            document.getElementById('installmentRequestSuccessMessage').textContent = payload.message;
            dialog.setAttribute('aria-labelledby', 'installmentRequestSuccessTitle');
            success.focus();
            document.dispatchEvent(new CustomEvent('installment-request-submitted', {detail: {clearCart: usesCart}}));
            form.reset();
            clearDocs();
        } catch (error) {
            errorBox.textContent = error.message || (isKmModal ? 'មិនអាចដាក់ស្នើសំណើរបស់អ្នកបានទេ។ សូមព្យាយាមម្តងទៀត។' : 'Unable to submit your request. Please try again.');
            errorBox.hidden = false;
        } finally {
            submitting = false;
            submit.disabled = false;
            submit.textContent = isKmModal ? 'ដាក់ស្នើការចុះឈ្មោះ និងកន្ត្រក' : 'Submit Registration & Cart';
        }
    });
})();
</script>
