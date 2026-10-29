@extends('loanmanagement::layouts.app')
@section('title', 'CMS & Public Portal Settings')

@section('loan_css')
<style>
    /* ========================================================
       ENTERPRISE CMS & PORTAL - MODERN EXECUTIVE REDESIGN
       ======================================================== */
    .lm-settings-wrapper {
        color: #0f172a;
        padding-bottom: 60px;
    }

    /* Page Header */
    .lm-settings-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }
    .lm-settings-header-left h1 {
        margin: 0;
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .lm-settings-header-left p {
        margin: 4px 0 0;
        font-size: 13px;
        color: #64748b;
    }
    .lm-settings-header-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .lm-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
    }
    .lm-chip i { font-size: 13px; color: var(--lm-primary, #6366f1); }
    .lm-chip-success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .lm-chip-success i { color: #059669; }
    .lm-chip-warning { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .lm-chip-warning i { color: #d97706; }

    /* Main Container Shell */
    .lm-settings-shell {
        display: grid;
        grid-template-columns: 290px minmax(0, 1fr);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    /* Nav Sidebar */
    .lm-settings-nav {
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        padding: 20px 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .lm-nav-group-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        padding: 6px 12px 8px;
        margin: 0;
    }
    .lm-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        margin-bottom: 4px;
        border-radius: 8px;
        color: #475569;
        font-size: 13.5px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid transparent;
    }
    .lm-nav-item:hover {
        background: #f1f5f9;
        color: #0f172a;
        text-decoration: none;
    }
    .lm-nav-item.active {
        background: #fff;
        color: var(--lm-primary, #6366f1);
        border-color: #e2e8f0;
        box-shadow: 0 2px 6px rgba(15,23,42,0.04);
        font-weight: 800;
    }
    .lm-nav-item i {
        font-size: 16px;
        width: 20px;
        text-align: center;
        color: #64748b;
        transition: color 0.15s ease;
    }
    .lm-nav-item.active i {
        color: var(--lm-primary, #6366f1);
    }
    .lm-nav-item .nav-badge {
        margin-left: auto;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #e2e8f0;
        color: #475569;
        font-weight: 700;
    }
    .lm-nav-item.active .nav-badge {
        background: var(--lm-primary-50, #eef2ff);
        color: var(--lm-primary, #6366f1);
    }

    /* Content Area */
    .lm-settings-content {
        padding: 32px 36px;
        min-height: 640px;
    }

    /* CMS Editor Grid */
    .lm-cms-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(360px, 0.9fr);
        gap: 28px;
        align-items: start;
    }

    /* Section Head */
    .lm-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9;
    }
    .lm-section-head h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
    }
    .lm-section-head p {
        margin: 4px 0 0;
        font-size: 12.5px;
        color: #64748b;
    }

    /* Form Fields */
    .lm-field {
        margin-bottom: 18px;
    }
    .lm-field-label {
        display: block;
        margin-bottom: 6px;
        color: #1e293b;
        font-size: 12.5px;
        font-weight: 800;
    }
    .lm-input, .lm-textarea, select.lm-input {
        width: 100%;
        height: 40px;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 13.5px;
        box-shadow: none;
        transition: all 0.15s ease;
    }
    .lm-input:focus, .lm-textarea:focus, select.lm-input:focus {
        border-color: var(--lm-primary, #6366f1);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }
    .lm-textarea {
        height: auto;
        min-height: 110px;
        resize: vertical;
        line-height: 1.55;
    }
    .lm-field-hint {
        margin-top: 5px;
        font-size: 11.5px;
        color: #64748b;
        line-height: 1.4;
    }

    /* Details Accordion */
    .lm-cms-group {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        margin-bottom: 14px;
        overflow: hidden;
        transition: all 0.15s ease;
    }
    .lm-cms-group summary {
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 800;
        color: #1e293b;
        cursor: pointer;
        outline: none;
        background: #f8fafc;
        border-bottom: 1px solid transparent;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .lm-cms-group[open] summary {
        background: #fff;
        border-bottom-color: #e2e8f0;
    }
    .lm-cms-group-body {
        padding: 16px;
        background: #fff;
    }

    /* Partner Rows */
    .lm-partner-row {
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .lm-partner-fields {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .lm-partner-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
    }
    .lm-partner-actions button {
        width: 30px;
        height: 30px;
        padding: 0;
        border-radius: 6px;
    }
    .lm-partner-toolbar {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .lm-partner-empty {
        padding: 16px 0;
        color: #94a3b8;
        font-size: 13px;
        text-align: center;
    }

    /* Live Preview Screen Container */
    .lm-preview-box {
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 10px 25px -5px rgba(15,23,42,0.1);
        position: sticky;
        top: 20px;
    }
    .lm-preview-browser-bar {
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
        padding: 8px 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .lm-browser-dot { width: 9px; height: 9px; border-radius: 50%; background: #cbd5e1; }
    .lm-browser-address {
        flex: 1;
        background: #fff;
        border-radius: 4px;
        padding: 3px 10px;
        font-size: 11px;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .lm-preview-hero {
        min-height: 280px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        color: #fff;
        background: linear-gradient(135deg, rgba(15,23,42,0.8), rgba(99,102,241,0.5)), var(--lm-hero-preview, #1e293b) center/cover;
        position: relative;
    }
    .lm-preview-brand {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        font-size: 14px;
    }
    .lm-preview-logo {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        overflow: hidden;
        background: rgba(255,255,255,0.2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 800;
    }
    .lm-preview-logo img { width: 100%; height: 100%; object-fit: contain; }
    .lm-preview-hero h3 {
        margin: 16px 0 6px;
        font-size: 20px;
        line-height: 1.3;
        font-weight: 800;
        letter-spacing: -0.01em;
    }
    .lm-preview-hero p {
        margin: 0;
        color: rgba(255,255,255,0.85);
        font-size: 12px;
        line-height: 1.5;
    }
    .lm-preview-products {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        padding: 14px;
        background: #f8fafc;
    }
    .lm-preview-product {
        height: 54px;
        border-radius: 6px;
        background: #fff;
        border: 1px solid #e2e8f0;
    }

    .lm-partner-preview {
        padding: 14px;
        background: #fff;
        text-align: center;
        border-top: 1px solid #f1f5f9;
    }
    .lm-partner-preview h4 {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: #94a3b8;
        margin: 0 0 10px;
    }
    .lm-partner-preview-list {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
    }
    .lm-partner-preview-list span {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        max-width: 90px;
        font-size: 11px;
        color: #475569;
        font-weight: 600;
    }
    .lm-partner-preview-list img { width: 70px; height: 26px; object-fit: contain; }

    /* Sticky Bottom Action Bar */
    .lm-sticky-actions {
        position: sticky;
        bottom: 16px;
        z-index: 100;
        margin-top: 24px;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 14px 24px;
        box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    @media (max-width: 1024px) {
        .lm-settings-shell { grid-template-columns: 1fr; }
        .lm-cms-workspace { grid-template-columns: 1fr; }
        .lm-settings-nav {
            border-right: 0;
            border-bottom: 1px solid #e2e8f0;
            flex-direction: row;
            overflow-x: auto;
            padding: 12px;
            gap: 6px;
        }
        .lm-nav-item { white-space: nowrap; margin-bottom: 0; }
        .lm-nav-divider, .lm-nav-group-title { display: none; }
        .lm-preview-box { position: static; margin-top: 20px; }
    }
</style>
@endsection

@section('content_body')
@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::logoUrl();
    $cmsEnabled = !empty($settings['cms_enabled']);
@endphp

<div class="lm-settings-wrapper">

    <!-- Top Header -->
    <div class="lm-settings-header">
        <div class="lm-settings-header-left">
            <h1><i class="fa fa-newspaper-o" style="color:var(--lm-primary); margin-right:8px;"></i> {{ $lmText('Public Homepage CMS & Catalog', 'គ្រប់គ្រងមាតិកាទំព័រដើម CMS') }}</h1>
            <p>{{ $lmText('Customize customer-facing hero banner, headlines, product brand highlights, and branch information.', 'កែសម្រួលផ្ទាំងរូបភាពទំព័រដើម ចំណងជើង ស្លាកយីហោទំនិញ និងព័ត៌មានសាខា។') }}</p>
        </div>
        <div class="lm-settings-header-chips">
            @if($cmsEnabled)
                <span class="lm-chip lm-chip-success"><i class="fa fa-globe"></i> {{ $lmText('Public Portal Active', 'ទំព័រដើមដំណើរការ') }}</span>
            @else
                <span class="lm-chip lm-chip-warning"><i class="fa fa-eye-slash"></i> {{ $lmText('Homepage Disabled (Directs to Login)', 'ទំព័រដើមបិទ (ទៅកាន់ Login)') }}</span>
            @endif
            <a href="{{ route('loan-management.public.home') }}" target="_blank" class="lm-chip" style="color:var(--lm-primary); text-decoration:none;">
                <i class="fa fa-external-link"></i> {{ $lmText('View Live Homepage', 'មើលទំព័រដើមផ្ទាល់') }}
            </a>
        </div>
    </div>

    @php
        $loanSessionStatus = session('status');
        $loanSessionStatusMessage = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'msg') : $loanSessionStatus;
        $loanSessionStatusSuccess = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'success', 1) : 1;
    @endphp
    @if($loanSessionStatusMessage)
        <div class="alert alert-{{ $loanSessionStatusSuccess ? 'success' : 'danger' }} alert-dismissible" style="border-radius:10px; margin-bottom:18px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-{{ $loanSessionStatusSuccess ? 'check-circle' : 'exclamation-circle' }}"></i> {{ $loanSessionStatusMessage }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius:10px; margin-bottom:18px;">
            <i class="fa fa-exclamation-triangle"></i> {{ $errors->first() }}
        </div>
    @endif

    @if(!$cmsEnabled)
        <div class="alert alert-warning" style="border-radius:10px; margin-bottom:18px;">
            <i class="fa fa-info-circle"></i>
            {{ $lmText('Public CMS homepage is currently disabled in Business Settings. You can still preview and edit content here; visitors will be redirected to employee login until enabled.', 'CMS ទំព័រដើមសាធារណៈត្រូវបានបិទក្នុង Business Settings។ អ្នកនៅតែអាចកែមាតិកានេះបាន ប៉ុន្តែអ្នកចូលមើលនឹងទៅទំព័រចូលប្រើបុគ្គលិករហូតដល់បើកវាឡើងវិញ។') }}
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('loan-management.settings.cms.update') }}" id="cmsSettingsForm">
        @csrf

        <div class="lm-settings-shell">
            <!-- Shared Sidebar Navigation Suite -->
            @include('loanmanagement::settings.partials.settings_nav', [
                'activeTab' => 'tab-cms',
                'isSinglePage' => false,
                'settings' => $settings,
            ])

            <!-- Main Content Area -->
            <main class="lm-settings-content">
                <div class="lm-cms-workspace">
                    <!-- Left: Form Controls -->
                    <div>
                        <div class="lm-section-head">
                            <div>
                                <h2>{{ $lmText('Hero Banner & Core Copy', 'ផ្ទាំងរូបភាព និងចំណងជើងទំព័រដើម') }}</h2>
                                <p>{{ $lmText('This content welcomes prospective borrowers visiting your website.', 'មាតិកានេះស្វាគមន៍អតិថិជនដែលចូលមកកាន់គេហទំព័រកម្ចីរបស់អ្នក។') }}</p>
                            </div>
                        </div>

                        <div class="lm-field">
                            <label class="lm-field-label" for="homeHeadlineInput">{{ $lmText('Main Headline', 'ចំណងជើងធំ') }} <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="lm-input" id="homeHeadlineInput" name="home_headline" maxlength="140" required value="{{ old('home_headline', $settings['home_headline']) }}" placeholder="e.g. Simple loan service for customers">
                            <div class="lm-field-hint">{{ $lmText('Appears prominently on the homepage hero fold.', 'បង្ហាញយ៉ាងលេចធ្លោនៅលើទំព័រដើម។') }}</div>
                        </div>

                        <div class="lm-field">
                            <label class="lm-field-label" for="homeSubtitleInput">{{ $lmText('Subtitle Description', 'អត្ថបទរង') }} <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="lm-input" id="homeSubtitleInput" name="home_subtitle" maxlength="220" required value="{{ old('home_subtitle', $settings['home_subtitle']) }}" placeholder="e.g. Choose your products and send an installment request...">
                        </div>

                        <div class="lm-field">
                            <label class="lm-field-label" for="homeBodyInput">{{ $lmText('About / Service Body Text', 'អត្ថបទពណ៌នាសេវាកម្មលម្អិត') }}</label>
                            <textarea class="lm-textarea" id="homeBodyInput" name="home_body" maxlength="1200" placeholder="Installment shopping, clear payment schedules, and personal support from our branch team...">{{ old('home_body', $settings['home_body']) }}</textarea>
                            <div class="lm-field-hint">{{ $lmText('Line breaks and paragraphs will be preserved on the public site.', 'ការចុះបន្ទាត់ និងកថាខណ្ឌនឹងត្រូវរក្សាទុកលើគេហទំព័រ។') }}</div>
                        </div>

                        <div class="lm-field" style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#f8fafc;">
                            <label class="lm-field-label" for="homeHeroInput">{{ $lmText('Homepage Hero Banner Image', 'រូបភាពបិទផ្ទាំងទំព័រដើម') }}</label>
                            <div style="margin-bottom:10px; border-radius:8px; overflow:hidden; border:1px solid #cbd5e1; max-height:160px;">
                                <img id="cmsHeroThumbnail" src="{{ route('loan-management.public.home-image') }}" alt="Current hero" style="width:100%; height:160px; object-fit:cover; display:block;">
                            </div>
                            <input type="file" id="homeHeroInput" name="home_hero" accept="image/jpeg,image/png,image/webp" class="lm-input" style="padding:6px 10px;">
                            <div class="lm-field-hint" style="margin-top:6px;">{{ $lmText('Landscape JPG, PNG, or WEBP. Max 50 MB.', 'រូបភាពផ្តេក JPG, PNG ឬ WEBP។ ទំហំអតិបរមា 50 MB។') }}</div>
                            @error('home_hero')<span class="text-danger" style="font-size:12px;">{{ $message }}</span>@enderror
                            <label style="font-size:12px; font-weight:700; color:#475569; margin-top:8px; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                <input type="checkbox" name="remove_home_hero" value="1"> {{ $lmText('Restore default template image', 'ប្រើរូបភាពលំនាំដើមឡើងវិញ') }}
                            </label>
                        </div>

                        <div class="lm-section-head" style="margin-top:28px;">
                            <div>
                                <h2>{{ $lmText('Sections & Feature Toggles', 'ផ្នែកទំព័រដើម និងការបើក/បិទមុខងារ') }}</h2>
                                <p>{{ $lmText('Control which modules appear on your public visitor portal.', 'កំណត់ថាតើផ្នែកណាខ្លះត្រូវបង្ហាញលើគេហទំព័រ។') }}</p>
                            </div>
                        </div>

                        @php($cmsValues = \Modules\LoanManagement\Services\CmsHomeService::normalize($settings['home_cms'] ?? []))
                        @foreach(\Modules\LoanManagement\Services\CmsHomeService::groups() as $group => $fields)
                            <details class="lm-cms-group" @if($loop->first || $group === 'Authorized Brands & Partners' || $group === 'Follow Us & Social Media') open @endif><summary>{{ $group }}</summary>
                                <div class="lm-cms-group-body">
                                    @foreach($fields as $key => [$label, $type, $default])
                                        <div class="lm-field">
                                            @if($type === 'boolean')
                                                <input type="hidden" name="home_cms[{{ $key }}]" value="0">
                                                <label for="cms_{{ $key }}" style="display:flex; align-items:center; gap:8px; font-weight:700; color:#1e293b; cursor:pointer;">
                                                    <input type="checkbox" id="cms_{{ $key }}" name="home_cms[{{ $key }}]" value="1" {{ old('home_cms.'.$key, $cmsValues[$key]) ? 'checked' : '' }}>
                                                    {{ $label }}
                                                </label>
                                            @elseif($type === 'select')
                                                <label class="lm-field-label" for="cms_{{ $key }}">{{ $label }}</label>
                                                <select class="lm-input" id="cms_{{ $key }}" name="home_cms[{{ $key }}]">
                                                    <option value="catalog" @selected(old('home_cms.'.$key, $cmsValues[$key]) === 'catalog')>Product Catalog Brands</option>
                                                    <option value="managed" @selected(old('home_cms.'.$key, $cmsValues[$key]) === 'managed')>Managed Brands &amp; Partners</option>
                                                </select>
                                            @elseif($type === 'collection')
                                                <label class="lm-field-label">{{ $label }}</label>
                                                <p class="text-muted" id="cmsPartnerStatus" role="status" style="font-size:12px; margin-bottom:8px;"></p>
                                                <input type="hidden" name="home_cms[brands_items]" value="">
                                                <div id="cmsPartnerRows">
                                                    @foreach(old('home_cms.brands_items', $cmsValues['brands_items']) ?: [] as $index => $partner)
                                                        @include('loanmanagement::settings.partials.cms_partner_row', ['index' => $index, 'partner' => $partner])
                                                    @endforeach
                                                </div>
                                                <div class="lm-partner-empty" id="cmsPartnerEmpty" hidden>No brands or partners configured.</div>
                                                <template id="cmsPartnerTemplate">@include('loanmanagement::settings.partials.cms_partner_row', ['index' => '__INDEX__', 'partner' => ['name' => '', 'logo_url' => '', 'website_url' => '', 'enabled' => true]])</template>
                                                <div class="lm-partner-toolbar">
                                                    <button type="button" class="btn btn-sm btn-default" id="cmsPartnerAdd"><i class="fa fa-plus"></i> Add Brand / Partner</button>
                                                    <button type="button" class="btn btn-sm btn-default" id="cmsPartnerSample"><i class="fa fa-download"></i> Import Sample Brands</button>
                                                </div>
                                            @else
                                                <label class="lm-field-label" for="cms_{{ $key }}">{{ $label }}</label>
                                                @if($type === 'textarea')
                                                    <textarea class="lm-textarea" id="cms_{{ $key }}" name="home_cms[{{ $key }}]" maxlength="1200">{{ old('home_cms.'.$key, $cmsValues[$key]) }}</textarea>
                                                @else
                                                    <input type="{{ $type }}" class="lm-input" id="cms_{{ $key }}" name="home_cms[{{ $key }}]" maxlength="220" value="{{ old('home_cms.'.$key, $cmsValues[$key]) }}">
                                                @endif
                                            @endif
                                            @error('home_cms.'.$key)<span class="text-danger" style="font-size:12px;">{{ $message }}</span>@enderror
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>

                    <!-- Right: Live Website Screen Preview Mockup -->
                    <div>
                        <div class="lm-section-head">
                            <div>
                                <h2>{{ $lmText('Live Page Mockup', 'គំរូទំព័រដើមជាក់ស្តែង') }}</h2>
                                <p>{{ $lmText('Real-time preview of public portal hero and partner logos.', 'មើលគំរូទំព័រដើម និងស្លាកយីហោជាក់ស្តែង។') }}</p>
                            </div>
                        </div>

                        <div class="lm-preview-box">
                            <div class="lm-preview-browser-bar">
                                <span class="lm-browser-dot"></span>
                                <span class="lm-browser-dot"></span>
                                <span class="lm-browser-dot"></span>
                                <span class="lm-browser-address">https://your-domain.com/</span>
                            </div>

                            <div class="lm-preview-hero" id="cmsPreviewHero" style="--lm-hero-preview: url('{{ route('loan-management.public.home-image') }}');">
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
                                    <p id="cmsPreviewSubtitle" style="margin-bottom:6px;">{{ old('home_subtitle', $settings['home_subtitle']) }}</p>
                                    <p id="cmsPreviewBody" style="opacity:0.8; font-size:11px;">{{ old('home_body', $settings['home_body']) }}</p>
                                </div>
                            </div>

                            <div class="lm-preview-products">
                                <div class="lm-preview-product"></div>
                                <div class="lm-preview-product"></div>
                                <div class="lm-preview-product"></div>
                            </div>

                            <div class="lm-partner-preview" id="cmsPartnerPreview">
                                <h4 id="cmsPartnerPreviewTitle"></h4>
                                <div class="lm-partner-preview-list" id="cmsPartnerPreviewList"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Bottom Action Bar -->
                <div class="lm-sticky-actions">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <a href="{{ route('loan-management.public.home') }}" target="_blank" class="btn btn-default" style="border-radius:8px; font-weight:700; padding:8px 16px;">
                            <i class="fa fa-external-link"></i> {{ $lmText('Open Homepage', 'បើកទំព័រដើម') }}
                        </a>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <a href="{{ route('loan-management.dashboard') }}" class="btn btn-default" style="border-radius:8px; font-weight:700; padding:8px 16px;">
                            {{ $lmText('Cancel', 'បោះបង់') }}
                        </a>
                        <button type="submit" class="btn btn-primary" id="saveCmsBtn" style="border-radius:8px; font-weight:800; padding:8px 22px;">
                            <i class="fa fa-save" style="margin-right:6px;"></i> {{ $lmText('Save CMS Changes', 'រក្សាទុក CMS') }}
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
            if (previewHeadline && headline) previewHeadline.textContent = headline.value || '';
            if (previewSubtitle && subtitle) previewSubtitle.textContent = subtitle.value || '';
            if (previewBody && body) previewBody.textContent = body.value || '';
        }

        [headline, subtitle, body].forEach(function (input) {
            if (input) input.addEventListener('input', syncPreview);
        });

        var heroInput = document.getElementById('homeHeroInput');
        var heroThumbnail = document.getElementById('cmsHeroThumbnail');
        var heroPreview = document.getElementById('cmsPreviewHero');

        if (heroInput) {
            heroInput.addEventListener('change', function () {
                var file = heroInput.files && heroInput.files[0];
                if (!file || !file.type.match(/^image\//)) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    var url = 'url("' + event.target.result + '")';
                    if (heroThumbnail) {
                        heroThumbnail.src = event.target.result;
                    }
                    if (heroPreview) {
                        heroPreview.style.setProperty('--lm-hero-preview', url);
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        syncPreview();

        var rows = document.getElementById('cmsPartnerRows');
        var partnerTemplate = document.getElementById('cmsPartnerTemplate');
        var source = document.getElementById('cms_brands_source');
        var partnerTitle = document.getElementById('cms_brands_title');
        var partnerVisibility = document.getElementById('cms_brands');
        var addPartner = document.getElementById('cmsPartnerAdd');
        var samplePartner = document.getElementById('cmsPartnerSample');

        function syncPartners() {
            if (!rows) return;
            var allRows = Array.from(rows.children);
            allRows.forEach(function (row, index) {
                row.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace(/brands_items\]\[[^\]]+\]/, 'brands_items][' + index + ']');
                });
                var upBtn = row.querySelector('[data-action="up"]');
                var downBtn = row.querySelector('[data-action="down"]');
                if (upBtn) upBtn.disabled = index === 0;
                if (downBtn) downBtn.disabled = index === allRows.length - 1;
            });
            if (addPartner) addPartner.disabled = allRows.length >= 50;
            var emptyNotice = document.getElementById('cmsPartnerEmpty');
            if (emptyNotice) emptyNotice.hidden = allRows.length !== 0;
            var visibleCount = allRows.filter(function (row) {
                var enabledCheckbox = row.querySelector('[data-field="enabled"]');
                return enabledCheckbox && enabledCheckbox.checked;
            }).length;
            var statusEl = document.getElementById('cmsPartnerStatus');
            if (statusEl && source) {
                statusEl.textContent = source.value === 'managed'
                    ? visibleCount + ' visible / ' + allRows.length + ' partners'
                    : 'Product catalog brands';
            }
            var preview = document.getElementById('cmsPartnerPreview');
            var list = document.getElementById('cmsPartnerPreviewList');
            var titleEl = document.getElementById('cmsPartnerPreviewTitle');
            if (titleEl && partnerTitle) titleEl.textContent = partnerTitle.value;
            if (list) {
                list.replaceChildren();
                var items = (source && source.value === 'catalog') ? [] : allRows.filter(function (row) {
                    var enabledCb = row.querySelector('[data-field="enabled"]');
                    return enabledCb && enabledCb.checked;
                }).map(function (row) {
                    var nameEl = row.querySelector('[data-field="name"]');
                    var logoEl = row.querySelector('[data-field="logo_url"]');
                    return {name: nameEl ? nameEl.value : '', logo: logoEl ? logoEl.value : ''};
                });
                items.forEach(function (item) {
                    if (!item.name.trim()) return;
                    var entry = document.createElement('span');
                    if (/^https?:\/\//i.test(item.logo || '')) {
                        var img = document.createElement('img');
                        img.src = item.logo;
                        img.alt = '';
                        img.onerror = function () { img.hidden = true; };
                        entry.appendChild(img);
                    }
                    entry.appendChild(document.createTextNode(item.name));
                    list.appendChild(entry);
                });
                if (preview && partnerVisibility) {
                    preview.hidden = !partnerVisibility.checked || !list.children.length;
                }
            }
        }

        function insertPartner(name) {
            if (!rows || rows.children.length >= 50 || !partnerTemplate) return;
            var fragment = partnerTemplate.content.cloneNode(true);
            var nameInput = fragment.querySelector('[data-field="name"]');
            if (nameInput) nameInput.value = name || '';
            rows.appendChild(fragment);
        }

        if (addPartner) {
            addPartner.addEventListener('click', function () {
                insertPartner('');
                if (source) source.value = 'managed';
                syncPartners();
                if (rows && rows.lastElementChild) {
                    var nInput = rows.lastElementChild.querySelector('[data-field="name"]');
                    if (nInput) nInput.focus();
                }
            });
        }

        if (samplePartner) {
            samplePartner.addEventListener('click', function () {
                var existing = Array.from(rows.querySelectorAll('[data-field="name"]')).map(function (input) { return input.value.trim().toLowerCase(); });
                @json(array_column(\Modules\LoanManagement\Services\CmsHomeService::sampleBrands(), 'name')).forEach(function (name) {
                    if (!existing.includes(name.toLowerCase())) insertPartner(name);
                });
                if (source) source.value = 'managed';
                syncPartners();
            });
        }

        if (rows) {
            rows.addEventListener('click', function (event) {
                var button = event.target.closest('[data-action]');
                if (!button) return;
                var row = button.closest('.lm-partner-row');
                if (source) source.value = 'managed';
                if (button.dataset.action === 'remove' && row) row.remove();
                if (button.dataset.action === 'up' && row && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
                if (button.dataset.action === 'down' && row && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
                syncPartners();
            });
            rows.addEventListener('input', function () {
                if (source) source.value = 'managed';
                syncPartners();
            });
        }

        [source, partnerTitle, partnerVisibility].forEach(function (input) {
            if (input) input.addEventListener('input', syncPartners);
        });

        syncPartners();

        var form = document.getElementById('cmsSettingsForm');
        var saveBtn = document.getElementById('saveCmsBtn');
        if (form && saveBtn) {
            form.addEventListener('submit', function () {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right:6px;"></i> Saving...';
            });
        }
    })();
</script>
@endsection
