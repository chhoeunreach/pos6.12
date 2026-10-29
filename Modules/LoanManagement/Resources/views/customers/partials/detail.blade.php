@php
    $isKhmer = session('user.language', config('app.locale')) === 'km';
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
    $display = fn ($value) => trim((string) ($value ?? '')) !== '' ? $value : '—';
    $primaryName = trim((string) ($customerRow->khmer_name ?? '')) ?: trim((string) ($customerRow->name ?? ''));
    $canEdit = auth()->user()->can('loan_management.customers.edit') || auth()->user()->can('loan_management.edit');
    $canViewLoan = auth()->user()->can('loan_management.loans.view') || auth()->user()->can('loan_management.view');
    $canViewPayment = auth()->user()->can('loan_management.payments.view') || auth()->user()->can('loan_management.payment') || auth()->user()->can('loan_management.view');
    $statusClass = fn ($value) => ['active' => 'success', 'paid' => 'success', 'completed' => 'success', 'closed' => 'neutral', 'inactive' => 'neutral', 'pending' => 'warning', 'overdue' => 'danger', 'defaulted' => 'danger', 'cancelled' => 'neutral'][$value] ?? 'info';
    $date = function ($value) { if (!$value) return '—'; try { return \Carbon\Carbon::parse($value)->format('d M Y'); } catch (\Throwable $e) { return '—'; } };
    $phone = preg_replace('/[^0-9+]/', '', (string) ($customerRow->phone ?? ''));
    $currencyGroups = collect($loanSummary['currencies'] ?? []);
    $infoGroups = [
        ['id' => 'identity', 'icon' => 'user', 'title' => $text('Personal information', 'ព័ត៌មានផ្ទាល់ខ្លួន'), 'fields' => [
            [$text('English name', 'ឈ្មោះអង់គ្លេស'), $customerRow->name ?? null],
            [$text('Khmer name', 'ឈ្មោះខ្មែរ'), $customerRow->khmer_name ?? null],
            [$text('Gender', 'ភេទ'), ucfirst($customerRow->gender ?? '')],
            [$text('Date of birth', 'ថ្ងៃខែឆ្នាំកំណើត'), $date($customerRow->date_of_birth ?? null)],
            [$text('ID card number', 'លេខអត្តសញ្ញាណប័ណ្ណ'), $customerRow->id_card_number ?? null],
            [$text('Passport number', 'លេខលិខិតឆ្លងដែន'), $customerRow->passport_number ?? null],
            [$text('Customer type', 'ប្រភេទអតិថិជន'), $customerRow->customer_type ?? null],
            [$text('Customer since', 'កាលបរិច្ឆេទចុះឈ្មោះ'), $date($customerRow->created_at ?? null)],
        ]],
        ['id' => 'contact', 'icon' => 'phone', 'title' => $text('Contact & address', 'ទំនាក់ទំនង និងអាសយដ្ឋាន'), 'fields' => [
            [$text('Phone number', 'លេខទូរស័ព្ទ'), $customerRow->phone ?? null],
            [$text('Alternative phone', 'លេខទូរស័ព្ទបន្ថែម'), $customerRow->alternate_phone ?? null],
            [$text('Email', 'អ៊ីមែល'), $customerRow->email ?? null],
            [$text('Telegram', 'តេឡេក្រាម'), $customerRow->telegram ?? null],
            [$text('Province', 'ខេត្ត'), $customerRow->province ?? null],
            [$text('District', 'ស្រុក / ខណ្ឌ'), $customerRow->district ?? null],
            [$text('Commune', 'ឃុំ / សង្កាត់'), $customerRow->commune ?? null],
            [$text('Village', 'ភូមិ'), $customerRow->village ?? null],
            [$text('Full address', 'អាសយដ្ឋានពេញ'), $customerRow->address ?? null],
        ]],
        ['id' => 'family', 'icon' => 'users', 'title' => $text('Family & employment', 'គ្រួសារ និងការងារ'), 'fields' => [
            [$text('Family contact', 'ឈ្មោះអ្នកទំនាក់ទំនងគ្រួសារ'), $customerRow->family_contact_name ?? null],
            [$text('Family phone', 'លេខទូរស័ព្ទគ្រួសារ'), $customerRow->family_contact_phone ?? null],
            [$text('Spouse name', 'ឈ្មោះប្តី / ប្រពន្ធ'), $customerRow->spouse_name ?? null],
            [$text('Spouse phone', 'លេខទូរស័ព្ទប្តី / ប្រពន្ធ'), $customerRow->spouse_phone ?? null],
            [$text('Workplace', 'កន្លែងធ្វើការ'), $customerRow->workplace ?? null],
            [$text('Monthly income', 'ប្រាក់ចំណូលប្រចាំខែ'), isset($customerRow->monthly_income) ? number_format((float) $customerRow->monthly_income, 2) : null],
        ]],
    ];
@endphp
<div class="lcd-page">
    @unless($customerModal ?? false)
    <nav class="lcd-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('loan-management.customers') }}">{{ $text('Customers', 'អតិថិជន') }}</a><i class="fa fa-angle-right" aria-hidden="true"></i><span>{{ $text('Customer detail', 'ព័ត៌មានលម្អិតអតិថិជន') }}</span></nav>
    @endunless
    <header class="lcd-profile">
        <div class="lcd-avatar">
            @if(!empty($customerPhotoUrl))
                <img src="{{ $customerPhotoUrl }}" alt="{{ $text('Customer profile photo', 'រូបថតអតិថិជន') }}" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
            @endif
            <i class="fa fa-user" aria-hidden="true" {{ !empty($customerPhotoUrl) ? 'hidden' : '' }}></i>
        </div>
        <div class="lcd-profile-info">
            <div class="lcd-eyebrow">{{ $text('Customer profile', 'ប្រវត្តិរូបអតិថិជន') }} <span>· {{ $display($customerRow->customer_code ?? null) }}</span></div>
            <h1>{{ $primaryName ?: $text('Customer', 'អតិថិជន') }}</h1>
            @if(!empty($customerRow->khmer_name) && !empty($customerRow->name))<p class="lcd-secondary-name">{{ $customerRow->name }}</p>@endif
            <div class="lcd-meta">
                <span class="lcd-badge lcd-badge-{{ $statusClass($customerRow->status ?? '') }}">{{ ucfirst($customerRow->status ?? 'Unknown') }}</span>
                @if(!empty($customerRow->blacklist_status))<span class="lcd-badge lcd-badge-danger"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ $text('Blacklisted', 'បញ្ជីខ្មៅ') }}</span>@endif
                <span><i class="fa fa-phone" aria-hidden="true"></i> {{ $display($customerRow->phone ?? null) }}</span>
            </div>
        </div>
        <div class="lcd-actions">
            @if($customerModal ?? false)<button type="button" class="lcd-button" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> {{ $text('Close', 'បិទ') }}</button>@else
            <a class="lcd-button" href="{{ route('loan-management.customers') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $text('Back to customers', 'ត្រឡប់ទៅអតិថិជន') }}</a>
            @endif
            @if($canEdit)<a class="lcd-button lcd-button-primary {{ ($customerModal ?? false) ? 'js-customer-modal' : '' }}" data-title="{{ $text('Edit Customer', 'កែប្រែអតិថិជន') }}" href="{{ route('loan-management.customers.edit', $customerRow->id) }}"><i class="fa fa-edit" aria-hidden="true"></i> {{ $text('Edit customer', 'កែប្រែអតិថិជន') }}</a>@endif
            @if($phone)<a class="lcd-button" href="tel:{{ $phone }}"><i class="fa fa-phone" aria-hidden="true"></i> {{ $text('Call customer', 'ហៅអតិថិជន') }}</a>@endif
        </div>
    </header>

    <section class="lcd-summary" aria-label="{{ $text('Financial overview', 'ទិដ្ឋភាពហិរញ្ញវត្ថុ') }}">
        <article class="lcd-stat"><span class="lcd-stat-icon"><i class="fa fa-file-alt" aria-hidden="true"></i></span><div><span class="lcd-stat-label">{{ $text('Total installments', 'កម្ចីសរុប') }}</span><strong>{{ number_format($loanSummary['count'] ?? 0) }}</strong><small>{{ $text('Full customer history', 'ប្រវត្តិអតិថិជនទាំងអស់') }}</small></div></article>
        <article class="lcd-stat"><span class="lcd-stat-icon lcd-stat-orange"><i class="fa fa-wallet" aria-hidden="true"></i></span><div><span class="lcd-stat-label">{{ $text('Outstanding balance', 'ប្រាក់នៅសល់') }}</span>@forelse($currencyGroups as $group)<strong class="lcd-money">{{ number_format((float) $group->balance, 2) }} <small>{{ $group->currency }}</small></strong>@empty<strong>0.00</strong>@endforelse<small>{{ $text('Grouped by installment currency', 'តាមរូបិយប័ណ្ណកម្ចី') }}</small></div></article>
        <article class="lcd-stat"><span class="lcd-stat-icon lcd-stat-green"><i class="fa fa-receipt" aria-hidden="true"></i></span><div><span class="lcd-stat-label">{{ $text('Payment records', 'ប្រតិបត្តិការបង់ប្រាក់') }}</span><strong>{{ number_format($paymentSummary['count'] ?? 0) }}</strong><small>{{ $text('Full payment history', 'ប្រវត្តិការបង់ប្រាក់ទាំងអស់') }}</small></div></article>
        <article class="lcd-stat"><span class="lcd-stat-icon lcd-stat-green"><i class="fa fa-money-bill-wave" aria-hidden="true"></i></span><div><span class="lcd-stat-label">{{ $text('Total collected', 'ប្រាក់ប្រមូលសរុប') }}</span><strong class="lcd-money">{{ number_format((float) ($paymentSummary['amount'] ?? 0), 2) }}</strong><small>{{ $text('Recorded base amounts', 'ចំនួនទឹកប្រាក់មូលដ្ឋាន') }}</small></div></article>
    </section>

    <nav class="lcd-section-links" aria-label="{{ $text('Customer sections', 'ផ្នែកព័ត៌មានអតិថិជន') }}">
        <a href="#customer-overview"><i class="fa fa-id-card" aria-hidden="true"></i> {{ $text('Overview', 'ព័ត៌មានទូទៅ') }}</a>
        <a href="#customer-installments"><i class="fa fa-file-alt" aria-hidden="true"></i> {{ $text('Installments', 'កម្ចី') }} <span>{{ $loanSummary['count'] ?? 0 }}</span></a>
        <a href="#customer-payments"><i class="fa fa-receipt" aria-hidden="true"></i> {{ $text('Payments', 'ការបង់ប្រាក់') }} <span>{{ $paymentSummary['count'] ?? 0 }}</span></a>
    </nav>

    <div class="lcd-content-grid" id="customer-overview">
        <div class="lcd-information">
            @foreach($infoGroups as $group)
                <section class="lcd-card" aria-labelledby="customer-{{ $group['id'] }}-heading">
                    <div class="lcd-card-head"><h2 id="customer-{{ $group['id'] }}-heading"><i class="fa fa-{{ $group['icon'] }}" aria-hidden="true"></i> {{ $group['title'] }}</h2></div>
                    <dl class="lcd-fields">@foreach($group['fields'] as [$label, $value])<div><dt>{{ $label }}</dt><dd>{{ $display($value) }}</dd></div>@endforeach</dl>
                </section>
            @endforeach
            <section class="lcd-card" aria-labelledby="customer-notes-heading"><div class="lcd-card-head"><h2 id="customer-notes-heading"><i class="fa fa-sticky-note" aria-hidden="true"></i> {{ $text('Notes & follow-up', 'កំណត់ចំណាំ និងការតាមដាន') }}</h2></div><p class="lcd-note">{{ trim((string) ($customerRow->note ?? '')) ?: $text('No notes have been added.', 'មិនទាន់មានកំណត់ចំណាំ។') }}</p></section>
        </div>
        <aside class="lcd-side">
            <section class="lcd-card" aria-labelledby="customer-access-heading">
                <div class="lcd-card-head"><h2 id="customer-access-heading"><i class="fa fa-shield-alt" aria-hidden="true"></i> {{ $text('Access & connections', 'សិទ្ធិចូលប្រើ និងការតភ្ជាប់') }}</h2></div>
                <dl class="lcd-access-list">
                    <div><dt>{{ $text('Customer app login', 'ការចូលប្រើកម្មវិធីអតិថិជន') }}</dt><dd class="lcd-badge lcd-badge-{{ !empty($customerRow->can_login) ? 'success' : 'neutral' }}">{{ !empty($customerRow->can_login) ? $text('Enabled', 'បានបើក') : $text('Disabled', 'បានបិទ') }}</dd></div>
                    <div><dt>{{ $text('GPS tracking', 'ការតាមដាន GPS') }}</dt><dd class="lcd-badge lcd-badge-{{ !empty($customerRow->allow_gps_tracking) ? 'info' : 'neutral' }}">{{ !empty($customerRow->allow_gps_tracking) ? $text('Enabled', 'បានបើក') : $text('Disabled', 'បានបិទ') }}</dd></div>
                    <div><dt>{{ $text('Telegram account', 'គណនី Telegram') }}</dt><dd class="lcd-badge lcd-badge-{{ !empty($customerRow->telegram_chat_id) ? 'success' : 'neutral' }}">{{ !empty($customerRow->telegram_chat_id) ? $text('Linked', 'បានភ្ជាប់') : $text('Not linked', 'មិនទាន់ភ្ជាប់') }}</dd></div>
                </dl>
                @if($canEdit)<p class="lcd-side-hint">{{ $text('Use Edit customer to manage profile and app access.', 'ប្រើកែប្រែអតិថិជនដើម្បីគ្រប់គ្រងព័ត៌មាន និងសិទ្ធិចូលប្រើ។') }}</p>@endif
            </section>
            @if(!empty($customerRow->blacklist_status))<section class="lcd-card lcd-risk"><div class="lcd-card-head"><h2><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ $text('Blacklist information', 'ព័ត៌មានបញ្ជីខ្មៅ') }}</h2></div><p class="lcd-note">{{ $display($customerRow->blacklist_reason ?? null) }}</p><p class="lcd-side-hint">{{ $text('Recorded on', 'កត់ត្រានៅ') }} {{ $date($customerRow->blacklist_date ?? null) }}</p></section>@endif
        </aside>
    </div>

    <section class="lcd-card" id="customer-installments" aria-labelledby="customer-installments-heading">
        <div class="lcd-card-head"><div><h2 id="customer-installments-heading"><i class="fa fa-file-alt" aria-hidden="true"></i> {{ $text('Installment history', 'ប្រវត្តិកម្ចី') }}</h2><p>{{ $text('Showing the latest', 'បង្ហាញចុងក្រោយ') }} {{ $loans->count() }} {{ $text('of', 'នៃ') }} {{ $loanSummary['count'] ?? 0 }}</p></div></div>
        <div class="lcd-table-scroll" tabindex="0" role="region" aria-label="{{ $text('Installment history', 'ប្រវត្តិកម្ចី') }}">
            <table class="lcd-table"><thead><tr><th scope="col">{{ $text('Installment number', 'លេខកម្ចី') }}</th><th scope="col">{{ $text('Date', 'កាលបរិច្ឆេទ') }}</th><th scope="col">{{ $text('Status', 'ស្ថានភាព') }}</th><th scope="col">{{ $text('Currency', 'រូបិយប័ណ្ណ') }}</th><th scope="col" class="lcd-number">{{ $text('Principal', 'ប្រាក់ដើម') }}</th><th scope="col" class="lcd-number">{{ $text('Balance', 'ប្រាក់នៅសល់') }}</th></tr></thead><tbody>
            @forelse($loans as $loan)<tr><td>@if($canViewLoan)<a class="lcd-record-link" href="{{ route('loan-management.loans.view', $loan->id) }}">{{ $loan->loan_number ?: '#'.$loan->id }} <i class="fa fa-angle-right" aria-hidden="true"></i></a>@else{{ $loan->loan_number ?: '#'.$loan->id }}@endif</td><td>{{ $date($loan->loan_date ?? $loan->created_at ?? null) }}</td><td><span class="lcd-badge lcd-badge-{{ $statusClass($loan->status ?? '') }}">{{ ucfirst($loan->status ?? 'Unknown') }}</span></td><td>{{ ($loan->currency ?? '') ?: 'USD' }}</td><td class="lcd-number">{{ number_format((float) ($loan->principal_amount ?? 0), 2) }}</td><td class="lcd-number lcd-balance">{{ number_format((float) ($loan->balance_amount ?? 0), 2) }}</td></tr>
            @empty<tr><td colspan="6"><div class="lcd-empty"><i class="fa fa-folder-open" aria-hidden="true"></i><strong>{{ $text('No installments yet', 'មិនទាន់មានកម្ចី') }}</strong><span>{{ $text('Installments linked to this customer will appear here.', 'កម្ចីរបស់អតិថិជននឹងបង្ហាញនៅទីនេះ។') }}</span></div></td></tr>@endforelse
            </tbody></table>
        </div>
    </section>
    <section class="lcd-card" id="customer-payments" aria-labelledby="customer-payments-heading">
        <div class="lcd-card-head"><div><h2 id="customer-payments-heading"><i class="fa fa-receipt" aria-hidden="true"></i> {{ $text('Payment history', 'ប្រវត្តិការបង់ប្រាក់') }}</h2><p>{{ $text('Showing the latest', 'បង្ហាញចុងក្រោយ') }} {{ $payments->count() }} {{ $text('of', 'នៃ') }} {{ $paymentSummary['count'] ?? 0 }} · {{ $text('Amounts shown in recorded base value', 'ចំនួនទឹកប្រាក់បង្ហាញជាតម្លៃមូលដ្ឋាន') }}</p></div></div>
        <div class="lcd-table-scroll" tabindex="0" role="region" aria-label="{{ $text('Payment history', 'ប្រវត្តិការបង់ប្រាក់') }}">
            <table class="lcd-table"><thead><tr><th scope="col">{{ $text('Receipt number', 'លេខបង្កាន់ដៃ') }}</th><th scope="col">{{ $text('Installment', 'កម្ចី') }}</th><th scope="col">{{ $text('Payment date', 'កាលបរិច្ឆេទបង់ប្រាក់') }}</th><th scope="col">{{ $text('Payment method', 'វិធីបង់ប្រាក់') }}</th><th scope="col" class="lcd-number">{{ $text('Base amount', 'ចំនួនទឹកប្រាក់មូលដ្ឋាន') }}</th></tr></thead><tbody>
            @forelse($payments as $payment)<tr><td>@if($canViewPayment)<a class="lcd-record-link" href="{{ route('loan-management.payments.show', $payment->id) }}">{{ ($payment->receipt_number ?? $payment->payment_ref_no ?? null) ?: '#'.$payment->id }} <i class="fa fa-angle-right" aria-hidden="true"></i></a>@else{{ ($payment->receipt_number ?? $payment->payment_ref_no ?? null) ?: '#'.$payment->id }}@endif</td><td>{{ $display($payment->loan_number ?? null) }}</td><td>{{ $date($payment->paid_date ?? $payment->paid_at ?? null) }}</td><td>{{ $display($payment->payment_method_snapshot ?? $payment->channel ?? $payment->method ?? null) }}</td><td class="lcd-number">{{ number_format((float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0), 2) }}</td></tr>
            @empty<tr><td colspan="5"><div class="lcd-empty"><i class="fa fa-receipt" aria-hidden="true"></i><strong>{{ $text('No payments yet', 'មិនទាន់មានការបង់ប្រាក់') }}</strong><span>{{ $text('Recorded customer payments will appear here.', 'ការបង់ប្រាក់របស់អតិថិជននឹងបង្ហាញនៅទីនេះ។') }}</span></div></td></tr>@endforelse
            </tbody></table>
        </div>
    </section>
</div>
