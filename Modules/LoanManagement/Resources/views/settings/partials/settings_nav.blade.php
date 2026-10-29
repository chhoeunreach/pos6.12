@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $currentActive = $activeTab ?? 'tab-profile';
    $isSinglePage = $isSinglePage ?? false;

    $businessUrl = route('loan-management.settings.business');
    $tabUrl = fn ($tabId) => $isSinglePage ? '#' . $tabId : $businessUrl . '#' . $tabId;
@endphp

<aside class="lm-settings-nav" id="lmSettingsNav">
    <div>
        <!-- GROUP 1: CORE CONFIGURATION -->
        <div class="lm-nav-group-title">{{ $lmText('Core Configuration', 'ការកំណត់ស្នូល') }}</div>

        <a href="{{ $tabUrl('tab-profile') }}"
           class="lm-nav-item {{ $currentActive === 'tab-profile' ? 'active' : '' }}"
           data-tab="tab-profile">
            <i class="fa fa-building-o"></i>
            <span>{{ $lmText('Company Profile', 'ព័ត៌មានក្រុមហ៊ុន') }}</span>
        </a>

        <a href="{{ $tabUrl('tab-policies') }}"
           class="lm-nav-item {{ $currentActive === 'tab-policies' ? 'active' : '' }}"
           data-tab="tab-policies">
            <i class="fa fa-balance-scale"></i>
            <span>{{ $lmText('Loan & Credit Policies', 'គោលការណ៍កម្ចី') }}</span>
            @if(isset($currentInterestRate))
                <span class="nav-badge">{{ $currentInterestRate }}%</span>
            @endif
        </a>

        <a href="{{ $tabUrl('tab-branding') }}"
           class="lm-nav-item {{ $currentActive === 'tab-branding' ? 'active' : '' }}"
           data-tab="tab-branding">
            <i class="fa fa-paint-brush"></i>
            <span>{{ $lmText('Branding & Look', 'រូបរាង & និមិត្តសញ្ញា') }}</span>
        </a>

        <a href="{{ $tabUrl('tab-currency') }}"
           class="lm-nav-item {{ $currentActive === 'tab-currency' ? 'active' : '' }}"
           data-tab="tab-currency">
            <i class="fa fa-hashtag"></i>
            <span>{{ $lmText('Currency & Codes', 'រូបិយប័ណ្ណ & លេខកូដ') }}</span>
            @if(isset($savedCurrencyCode))
                <span class="nav-badge">{{ $savedCurrencyCode }}</span>
            @endif
        </a>

        <!-- GROUP 2: FINANCIAL OPERATIONS -->
        <div class="lm-nav-divider"></div>
        <div class="lm-nav-group-title">{{ $lmText('Financial Operations', 'ប្រតិបត្តិការហិរញ្ញវត្ថុ') }}</div>

        <a href="{{ $tabUrl('tab-payment') }}"
           class="lm-nav-item {{ in_array($currentActive, ['tab-payment', 'payment-methods'], true) ? 'active' : '' }}"
           data-tab="tab-payment">
            <i class="fa fa-credit-card"></i>
            <span>{{ $lmText('Payment Methods', 'វិធីសាស្ត្របង់ប្រាក់') }}</span>
            @if(isset($activeMethodsCount))
                <span class="nav-badge">{{ $activeMethodsCount }}</span>
            @elseif(isset($activeCount))
                <span class="nav-badge">{{ $activeCount }}</span>
            @endif
        </a>

        <a href="{{ $tabUrl('tab-templates') }}"
           class="lm-nav-item {{ $currentActive === 'tab-templates' ? 'active' : '' }}"
           data-tab="tab-templates">
            <i class="fa fa-file-text-o"></i>
            <span>{{ $lmText('Contracts & Printing', 'កិច្ចសន្យា & វិក្កយបត្រ') }}</span>
        </a>

        <!-- GROUP 3: GATEWAYS & PORTALS -->
        <div class="lm-nav-divider"></div>
        <div class="lm-nav-group-title">{{ $lmText('Gateways & Web', 'ច្រកទិន្នន័យ & គេហទំព័រ') }}</div>

        <a href="{{ $tabUrl('tab-notifications') }}"
           class="lm-nav-item {{ $currentActive === 'tab-notifications' ? 'active' : '' }}"
           data-tab="tab-notifications">
            <i class="fa fa-telegram"></i>
            <span>{{ $lmText('Telegram Alerts', 'ការជូនដំណឹង Telegram') }}</span>
            @if(!empty($settings['telegram_bot_token']))
                <span class="nav-badge" style="background:#ecfdf5; color:#047857;">ON</span>
            @endif
        </a>

        <a href="{{ $tabUrl('tab-social') }}"
           class="lm-nav-item {{ in_array($currentActive, ['tab-social', 'social'], true) ? 'active' : '' }}"
           data-tab="tab-social">
            <i class="fa fa-share-alt"></i>
            <span>{{ $lmText('Social Media (Follow Us)', 'បណ្ដាញសង្គម (Follow Us)') }}</span>
            @php
                $activeSocialCount = 0;
                $cmsData = $settings['home_cms'] ?? [];
                foreach (['footer_facebook', 'footer_telegram', 'footer_tiktok', 'footer_instagram', 'footer_youtube', 'footer_whatsapp', 'footer_linkedin', 'footer_twitter', 'footer_website'] as $sKey) {
                    if (!empty($cmsData[$sKey])) $activeSocialCount++;
                }
            @endphp
            @if($activeSocialCount > 0)
                <span class="nav-badge" style="background:#ecfdf5; color:#047857;">{{ $activeSocialCount }}</span>
            @endif
        </a>

        <a href="{{ $tabUrl('tab-cms') }}"
           class="lm-nav-item {{ in_array($currentActive, ['tab-cms', 'cms'], true) ? 'active' : '' }}"
           data-tab="tab-cms">
            <i class="fa fa-newspaper-o"></i>
            <span>{{ $lmText('CMS & Public Portal', 'CMS & ទំព័រដើម') }}</span>
            <span class="nav-badge" style="{{ !empty($settings['cms_enabled']) ? 'background:#ecfdf5; color:#047857;' : '' }}">
                {{ !empty($settings['cms_enabled']) ? 'ON' : 'OFF' }}
            </span>
        </a>

        <a href="{{ $tabUrl('tab-portal') }}"
           class="lm-nav-item {{ $currentActive === 'tab-portal' ? 'active' : '' }}"
           data-tab="tab-portal">
            <i class="fa fa-lock"></i>
            <span>{{ $lmText('Access & Security', 'ច្រកចូល & សុវត្ថិភាព') }}</span>
        </a>

        <!-- GROUP 4: ENTERPRISE EXTENSIONS (RECOMMENDED) -->
        <div class="lm-nav-divider"></div>
        <div class="lm-nav-group-title">{{ $lmText('Enterprise Modules', 'ម៉ូឌុលបន្ថែម') }}</div>

        <a href="{{ route('loan-management.locations.index') }}" class="lm-nav-item">
            <i class="fa fa-building"></i>
            <span>{{ $lmText('Branch Locations', 'សាខាប្រតិបត្តិការ') }}</span>
            <i class="fa fa-angle-right" style="margin-left:auto; font-size:12px; opacity:0.5;"></i>
        </a>

        <a href="{{ route('loan-management.activity-logs.index') }}" class="lm-nav-item">
            <i class="fa fa-history"></i>
            <span>{{ $lmText('Audit Logs & Trail', 'កំណត់ហេតុសវនកម្ម') }}</span>
            <i class="fa fa-angle-right" style="margin-left:auto; font-size:12px; opacity:0.5;"></i>
        </a>

        <a href="{{ route('loan-management.system.status') }}" class="lm-nav-item">
            <i class="fa fa-heartbeat"></i>
            <span>{{ $lmText('System Health', 'សុខភាពប្រព័ន្ធ') }}</span>
            <i class="fa fa-angle-right" style="margin-left:auto; font-size:12px; opacity:0.5;"></i>
        </a>

        <a href="{{ route('loan-management.reports.cbc-export') }}" class="lm-nav-item">
            <i class="fa fa-file-excel-o"></i>
            <span>{{ $lmText('CBC Credit Export', 'របាយការណ៍ CBC') }}</span>
            <i class="fa fa-angle-right" style="margin-left:auto; font-size:12px; opacity:0.5;"></i>
        </a>
    </div>

    @if(!empty($settings['system_name']))
        <div style="margin-top:20px; padding-top:14px; border-top:1px solid #e2e8f0;">
            <div class="lm-nav-group-title">{{ $lmText('System Instance', 'ព័ត៌មានប្រព័ន្ធ') }}</div>
            <div style="padding:8px 12px; font-size:12px; color:#64748b; line-height:1.4;">
                <strong style="color:#0f172a; display:block; font-size:13px;">{{ $settings['system_name'] }}</strong>
                <span>{{ $settings['business_name'] ?? '' }}</span>
            </div>
        </div>
    @endif
</aside>
