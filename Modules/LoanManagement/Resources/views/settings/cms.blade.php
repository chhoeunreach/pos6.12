@extends('loanmanagement::layouts.app')

@section('title', 'CMS Settings')

@section('content_header')
    <h1>CMS Settings</h1>
@endsection

@section('content_body')
@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::logoUrl();
    $settingsTabs = [
        'Business' => route('loan-management.settings.business'),
        'CMS' => route('loan-management.settings.cms'),
        'Payment' => route('loan-management.settings.payment-methods'),
    ];
@endphp

<style>
    .lm-cms-page { padding-bottom: 36px; }
    .lm-cms-title { margin: 0 0 18px; font-size: 26px; font-weight: 800; color: #111827; letter-spacing: 0; }
    .lm-cms-card { display: grid; grid-template-columns: 230px minmax(0, 1fr); background: #fff; border: 1px solid #dfe7f1; border-radius: 8px; box-shadow: 0 12px 34px rgba(15,23,42,.06); overflow: hidden; }
    .lm-cms-tabs { background: #f8fafc; border-right: 1px solid #e2e8f0; padding: 18px 12px; }
    .lm-cms-tab { display: block; padding: 12px 14px; margin-bottom: 7px; border-radius: 6px; color: #334155; font-weight: 800; text-decoration: none; }
    .lm-cms-tab:hover, .lm-cms-tab.active { background: var(--lm-primary, #2563eb); color: #fff; text-decoration: none; }
    .lm-cms-content { padding: 24px; }
    .lm-cms-grid { display: grid; grid-template-columns: minmax(0, .9fr) minmax(360px, .8fr); gap: 22px; align-items: start; }
    .lm-cms-section-title { margin: 0 0 16px; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: 0; }
    .lm-field { margin-bottom: 16px; }
    .lm-field label { display: block; margin-bottom: 7px; color: #334155; font-size: 12px; font-weight: 800; text-transform: uppercase; }
    .lm-input, .lm-textarea { width: 100%; border: 1px solid #d7e1ec; border-radius: 6px; padding: 11px 12px; color: #0f172a; outline: 0; box-shadow: none; }
    .lm-input:focus, .lm-textarea:focus { border-color: var(--lm-primary, #2563eb); box-shadow: 0 0 0 3px rgba(var(--lm-primary-rgb, 37,99,235), .12); }
    .lm-textarea { min-height: 190px; resize: vertical; line-height: 1.55; }
    .lm-help { color: #64748b; font-size: 12px; line-height: 1.5; }
    .lm-preview { border: 1px solid #dbe4ef; border-radius: 8px; overflow: hidden; background: #f8fafc; }
    .lm-preview-hero { min-height: 330px; padding: 24px; display: flex; flex-direction: column; justify-content: space-between; color: #fff; background: linear-gradient(135deg, #102033, var(--lm-primary, #2563eb)); }
    .lm-preview-brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 800; }
    .lm-preview-logo { width: 38px; height: 38px; border-radius: 8px; overflow: hidden; background: rgba(255,255,255,.18); display: inline-flex; align-items: center; justify-content: center; }
    .lm-preview-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lm-preview-hero h3 { margin: 26px 0 0; font-size: 34px; line-height: 1.08; font-weight: 900; letter-spacing: 0; }
    .lm-preview-hero p { margin: 10px 0 0; color: rgba(255,255,255,.82); line-height: 1.6; }
    .lm-preview-products { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 12px; }
    .lm-preview-product { height: 74px; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; }
    .lm-actions { margin-top: 20px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .lm-actions-left, .lm-actions-right { display: flex; gap: 10px; flex-wrap: wrap; }
    @media (max-width: 980px) {
        .lm-cms-card, .lm-cms-grid { grid-template-columns: 1fr; }
        .lm-cms-tabs { border-right: 0; border-bottom: 1px solid #e2e8f0; }
    }
</style>

<div class="lm-cms-page">
    <h1 class="lm-cms-title">{{ $lmText('CMS Manager', 'គ្រប់គ្រង CMS') }}</h1>

    @php
        $loanSessionStatus = session('status');
        $loanSessionStatusMessage = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'msg') : $loanSessionStatus;
        $loanSessionStatusSuccess = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'success', 1) : 1;
    @endphp
    @if($loanSessionStatusMessage)
        <div class="alert alert-{{ $loanSessionStatusSuccess ? 'success' : 'danger' }}">{{ $loanSessionStatusMessage }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if(empty($settings['cms_enabled']))
        <div class="alert alert-warning">
            {{ $lmText('Public CMS homepage is disabled in Business Settings. You can still edit this content, but visitors will be sent to admin login until it is enabled.', 'CMS ទំព័រដើមសាធារណៈត្រូវបានបិទក្នុង Business Settings។ អ្នកនៅតែអាចកែមាតិកានេះបាន ប៉ុន្តែអ្នកចូលមើលនឹងទៅទំព័រចូលប្រើអ្នកគ្រប់គ្រងរហូតដល់បើកវាវិញ។') }}
        </div>
    @endif

    <form method="POST" action="{{ route('loan-management.settings.cms.update') }}">
        @csrf
        <div class="lm-cms-card">
            <aside class="lm-cms-tabs">
                @foreach($settingsTabs as $tab => $route)
                    <a href="{{ $route }}" class="lm-cms-tab {{ $tab === 'CMS' ? 'active' : '' }}">{{ $tab }}</a>
                @endforeach
            </aside>

            <main class="lm-cms-content">
                <div class="lm-cms-grid">
                    <section>
                        <h2 class="lm-cms-section-title">{{ $lmText('Public Homepage Content', 'មាតិកាទំព័រដើមសាធារណៈ') }}</h2>

                        <div class="lm-field">
                            <label for="homeHeadlineInput">{{ $lmText('Headline', 'ចំណងជើង') }}</label>
                            <input type="text" class="lm-input" id="homeHeadlineInput" name="home_headline" maxlength="140" required value="{{ old('home_headline', $settings['home_headline']) }}">
                            <div class="lm-help">{{ $lmText('Main headline shown in the first screen of the public homepage.', 'ចំណងជើងសំខាន់ដែលបង្ហាញនៅលើទំព័រដើមសាធារណៈ។') }}</div>
                        </div>

                        <div class="lm-field">
                            <label for="homeSubtitleInput">{{ $lmText('Subtitle', 'អត្ថបទរង') }}</label>
                            <input type="text" class="lm-input" id="homeSubtitleInput" name="home_subtitle" maxlength="220" required value="{{ old('home_subtitle', $settings['home_subtitle']) }}">
                        </div>

                        <div class="lm-field">
                            <label for="homeBodyInput">{{ $lmText('Body Text', 'អត្ថបទលម្អិត') }}</label>
                            <textarea class="lm-textarea" id="homeBodyInput" name="home_body" maxlength="1200">{{ old('home_body', $settings['home_body']) }}</textarea>
                            <div class="lm-help">{{ $lmText('Use this for customer-facing service details. Line breaks are preserved.', 'ប្រើសម្រាប់ពណ៌នាសេវាកម្មអតិថិជន។ ការចុះបន្ទាត់នឹងរក្សាទុក។') }}</div>
                        </div>
                    </section>

                    <aside>
                        <h2 class="lm-cms-section-title">{{ $lmText('Live Preview', 'មើលគំរូ') }}</h2>
                        <div class="lm-preview">
                            <div class="lm-preview-hero">
                                <div class="lm-preview-brand">
                                    <span class="lm-preview-logo">
                                        @if($businessLogoUrl)
                                            <img src="{{ $businessLogoUrl }}" alt="{{ $settings['business_name'] }}">
                                        @else
                                            {{ strtoupper(mb_substr($settings['business_name'], 0, 1)) }}
                                        @endif
                                    </span>
                                    <span>{{ $settings['business_name'] }}</span>
                                </div>
                                <div>
                                    <h3 id="cmsPreviewHeadline">{{ old('home_headline', $settings['home_headline']) }}</h3>
                                    <p id="cmsPreviewSubtitle">{{ old('home_subtitle', $settings['home_subtitle']) }}</p>
                                    <p id="cmsPreviewBody">{{ old('home_body', $settings['home_body']) }}</p>
                                </div>
                            </div>
                            <div class="lm-preview-products">
                                <div class="lm-preview-product"></div>
                                <div class="lm-preview-product"></div>
                                <div class="lm-preview-product"></div>
                            </div>
                        </div>
                    </aside>
                </div>

                <div class="lm-actions">
                    <div class="lm-actions-left">
                        <a href="{{ route('loan-management.public.home') }}" target="_blank" class="btn btn-default">
                            <i class="fa fa-external-link"></i> {{ $lmText('Open Homepage', 'បើកទំព័រដើម') }}
                        </a>
                    </div>
                    <div class="lm-actions-right">
                        <a href="{{ route('loan-management.dashboard') }}" class="btn btn-default">{{ $lmText('Cancel', 'បោះបង់') }}</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> {{ $lmText('Save CMS', 'រក្សាទុក CMS') }}
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </form>
</div>
@endsection

@section('loan_js')
<script>
    (function () {
        var headline = document.getElementById('homeHeadlineInput');
        var subtitle = document.getElementById('homeSubtitleInput');
        var body = document.getElementById('homeBodyInput');
        var previewHeadline = document.getElementById('cmsPreviewHeadline');
        var previewSubtitle = document.getElementById('cmsPreviewSubtitle');
        var previewBody = document.getElementById('cmsPreviewBody');

        function syncPreview() {
            previewHeadline.textContent = headline.value || '';
            previewSubtitle.textContent = subtitle.value || '';
            previewBody.textContent = body.value || '';
        }

        [headline, subtitle, body].forEach(function (input) {
            input.addEventListener('input', syncPreview);
        });
        syncPreview();
    })();
</script>
@endsection
