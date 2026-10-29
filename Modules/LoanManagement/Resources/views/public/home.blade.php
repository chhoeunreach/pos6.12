@php
    $businessLogoUrl = \Modules\LoanManagement\Services\BusinessSettingsService::publicLogoUrl();
    $businessName = \Modules\LoanManagement\Services\BusinessSettingsService::businessName();
    $loanLanguage = session('user.language') ?? request()->cookie('lm_lang') ?? request('lang') ?? config('app.locale', 'en');
    $loanLanguage = in_array($loanLanguage, ['en', 'km'], true) ? $loanLanguage : 'en';
    $lmIsKhmer = $loanLanguage === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $headline = $settings['home_headline'] ?? 'Simple loan service for customers';
    if ($lmIsKhmer && $headline === 'Simple loan service for customers') {
        $headline = 'សេវាកម្មបង់រំលស់ងាយស្រួលសម្រាប់អតិថិជន';
    }
    $subtitle = $settings['home_subtitle'] ?? '';
    $body = $settings['home_body'] ?? '';
    $cms = \Modules\LoanManagement\Services\CmsHomeService::normalize($settings['home_cms'] ?? []);

    if ($lmIsKhmer) {
        if ($cms['tagline'] === 'Showroom & Installment') $cms['tagline'] = 'បន្ទប់តាំងបង្ហាញ & សេវាកម្មបង់រំលស់';
        if ($cms['announcement_text'] === 'Showroom & Installment') $cms['announcement_text'] = 'បន្ទប់តាំងបង្ហាញ & សេវាកម្មបង់រំលស់';
        if ($cms['hero_eyebrow'] === 'Installment Shopping') $cms['hero_eyebrow'] = 'ទិញទំនិញបង់រំលស់';
        if ($cms['assessment_title'] === 'Instant Loan Assessment') $cms['assessment_title'] = 'គណនាប្រាក់បង់រំលស់ភ្លាមៗ';
        if ($cms['assessment_note'] === 'Estimate before interest and fees. Our staff confirms final installment terms after review.') {
            $cms['assessment_note'] = 'ការប៉ាន់ស្មានមុនការប្រាក់ និងថ្លៃសេវា។ បុគ្គលិកយើងនឹងបញ្ជាក់លក្ខខណ្ឌចុងក្រោយបន្ទាប់ពីពិនិត្យ។';
        }
        if ($cms['brands_title'] === 'Authorized Brands & Partners') $cms['brands_title'] = 'ម៉ាកយីហោ និងដៃគូសហការ';
        if ($cms['guide_title'] === 'Apply in a few minutes') $cms['guide_title'] = 'ស្នើសុំត្រឹមតែប៉ុន្មាននាទី';
        if ($cms['guide_description'] === 'Choose a product, submit your request, and our staff will follow up.') {
            $cms['guide_description'] = 'ជ្រើសរើសទំនិញ បញ្ជូនសំណើរបស់អ្នក ហើយបុគ្គលិកយើងនឹងទាក់ទងមកវិញ។';
        }
        if ($cms['step_1_title'] === 'Choose product') $cms['step_1_title'] = 'ជ្រើសរើសទំនិញ';
        if ($cms['step_1_body'] === 'Add products from the catalog to your installment cart.' || $cms['step_1_body'] === 'Browse the catalog and add your selected products to the installment cart.') {
            $cms['step_1_body'] = 'ជ្រើសរើសទំនិញពីកាតាឡុកដាក់ចូលក្នុងកន្ត្រកបង់រំលស់។';
        }
        if ($cms['step_2_title'] === 'Submit request') $cms['step_2_title'] = 'បញ្ជូនសំណើ';
        if ($cms['step_2_body'] === 'Send your name, phone number, and contact address with your selected items.' || $cms['step_2_body'] === 'Send your contact details and selected items to our team.') {
            $cms['step_2_body'] = 'ផ្ញើព័ត៌មានទំនាក់ទំនង និងទំនិញដែលបានជ្រើសរើសទៅកាន់ក្រុមការងារយើង។';
        }
        if ($cms['step_3_title'] === 'Staff follow-up') $cms['step_3_title'] = 'បុគ្គលិកទាក់ទងមកវិញ';
        if ($cms['step_3_body'] === 'Our staff will contact you to review your request and discuss installment terms.' || $cms['step_3_body'] === 'Our staff will contact you to review your pending request.') {
            $cms['step_3_body'] = 'បុគ្គលិកយើងនឹងទាក់ទងមកដើម្បីពិនិត្យសំណើដែលកំពុងរង់ចាំរបស់អ្នក។';
        }
        if ($cms['experience_title'] === 'Simple customer experience') $cms['experience_title'] = 'បទពិសោធន៍អតិថិជនងាយស្រួល';
        if ($cms['experience_description'] === 'Browse products, request installment service, and return to your customer account once staff has enabled access.') {
            $cms['experience_description'] = 'ស្វែងរកទំនិញ ស្នើសុំសេវាបង់រំលស់ និងចូលទៅកាន់គណនីអតិថិជនបន្ទាប់ពីបុគ្គលិកបានអនុញ្ញាត។';
        }
        if ($cms['experience_1_title'] === 'Choose product') $cms['experience_1_title'] = 'ជ្រើសរើសទំនិញ';
        if ($cms['experience_1_body'] === 'Add products from the catalog to your installment cart.') $cms['experience_1_body'] = 'ជ្រើសរើសទំនិញពីកាតាឡុកដាក់ចូលក្នុងកន្ត្រកបង់រំលស់។';
        if ($cms['experience_2_title'] === 'Submit request') $cms['experience_2_title'] = 'បញ្ជូនសំណើ';
        if ($cms['experience_2_body'] === 'Send your contact details and selected items. Our staff will follow up.') $cms['experience_2_body'] = 'ផ្ញើព័ត៌មានទំនាក់ទំនង និងទំនិញដែលបានជ្រើសរើស។ បុគ្គលិកយើងនឹងទាក់ទងមកវិញ។';
        if ($cms['experience_3_title'] === 'Track your account') $cms['experience_3_title'] = 'តាមដានគណនីរបស់អ្នក';
        if ($cms['experience_3_body'] === 'After staff enables your account, log in to view loan records and payment history.') $cms['experience_3_body'] = 'បន្ទាប់ពីបុគ្គលិកបើកគណនីរបស់អ្នក សូមចូលដើម្បីមើលកំណត់ត្រាកម្ចី និងប្រវត្តិបង់ប្រាក់។';
        if ($cms['catalog_title'] === 'Products Catalog') $cms['catalog_title'] = 'កាតាឡុកទំនិញ';
        if ($cms['catalog_description'] === 'Find your next product and submit an installment request. All requests are subject to review.') {
            $cms['catalog_description'] = 'ស្វែងរកទំនិញ និងបញ្ជូនសំណើបង់រំលស់។ សំណើទាំងអស់ត្រូវឆ្លងកាត់ការពិនិត្យ។';
        }
        if ($cms['about_title'] === 'About Us') $cms['about_title'] = 'អំពីយើង';
        if ($cms['contact_title'] === 'Contact Us') $cms['contact_title'] = 'ទាក់ទងមកយើង';
        if ($cms['privacy_title'] === 'Privacy Policy') $cms['privacy_title'] = 'គោលការណ៍ភាពឯកជន';
        if ($cms['privacy_body'] === 'Contact our showroom team for information about our privacy policy.') {
            $cms['privacy_body'] = 'សូមទាក់ទងក្រុមការងារបន្ទប់តាំងបង្ហាញរបស់យើងសម្រាប់ព័ត៌មានអំពីគោលការណ៍ភាពឯកជន។';
        }
        if ($cms['footer_text'] === 'For your applications, installment account, and payment history, visit the Customer Portal.') {
            $cms['footer_text'] = 'សម្រាប់ពាក្យស្នើសុំ គណនីបង់រំលស់ និងប្រវត្តិបង់ប្រាក់ សូមចូលទៅកាន់ផតថលអតិថិជន។';
        }
        if ($cms['footer_note'] === 'All installment requests are subject to review and approval by our team.') {
            $cms['footer_note'] = 'សំណើបង់រំលស់ទាំងអស់ស្ថិតក្រោមការពិនិត្យ និងអនុម័តដោយក្រុមការងាររបស់យើង។';
        }
        if ($cms['footer_links_title'] === 'Quick Links') $cms['footer_links_title'] = 'តំណភ្ជាប់រហ័ស';
        if ($cms['footer_accounts_title'] === 'Accounts') $cms['footer_accounts_title'] = 'គណនី';
        if ($cms['footer_contact_title'] === 'Get In Touch') $cms['footer_contact_title'] = 'ទាក់ទងមកយើង';
        if ($cms['footer_social_title'] === 'Follow Us') $cms['footer_social_title'] = 'តាមដានពួកយើង';
    }

    $customerPortalEnabled = \Modules\LoanManagement\Services\BusinessSettingsService::isCustomerLoginEnabled();
    $partnerBrands = \Modules\LoanManagement\Services\CmsHomeService::visibleBrands($cms, $brands ?? []);
    $menuSections = ['home' => 'hero', 'products' => 'catalog', 'how' => 'guide', 'cart' => 'cart', 'about' => 'about', 'contact' => 'contact'];
    $visibleMenu = array_filter($menuSections, fn ($section, $key) => $cms['menu_'.$key] && $cms[$section] && ($key !== 'cart' || $cms['catalog']), ARRAY_FILTER_USE_BOTH);

    $customerUser = Auth::guard('customer_loan')->user();
    $adminUser = Auth::guard('web')->user() ?? Auth::user();

    $customerPhotoUrl = $customerUser ? $customerUser->customer_photo_url : null;
    $adminPhotoUrl = null;
    if ($adminUser) {
        if (!empty($adminUser->profile_photo_url)) {
            $adminPhotoUrl = $adminUser->profile_photo_url;
        } elseif (!empty($adminUser->profile_photo)) {
            $adminPhotoUrl = asset('uploads/profile_photos/' . $adminUser->profile_photo);
        } elseif (session()->has('user.profile_photo_url')) {
            $adminPhotoUrl = session('user.profile_photo_url');
        }
    }

    $footerUrl = function ($value) {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        return preg_match('#^(https?:)?//#i', $url) ? $url : 'https://'.$url;
    };
    $footerSocials = array_values(array_filter([
        ['url' => $footerUrl($cms['footer_facebook']), 'icon' => 'fa-brands fa-facebook-f', 'label' => 'Facebook'],
        ['url' => $footerUrl($cms['footer_telegram']), 'icon' => 'fa-brands fa-telegram', 'label' => 'Telegram'],
        ['url' => $footerUrl($cms['footer_tiktok']), 'icon' => 'fa-brands fa-tiktok', 'label' => 'TikTok'],
        ['url' => $footerUrl($cms['footer_instagram']), 'icon' => 'fa-brands fa-instagram', 'label' => 'Instagram'],
        ['url' => $footerUrl($cms['footer_youtube']), 'icon' => 'fa-brands fa-youtube', 'label' => 'YouTube'],
        ['url' => $footerUrl($cms['footer_whatsapp'] ?? ''), 'icon' => 'fa-brands fa-whatsapp', 'label' => 'WhatsApp'],
        ['url' => $footerUrl($cms['footer_linkedin'] ?? ''), 'icon' => 'fa-brands fa-linkedin-in', 'label' => 'LinkedIn'],
        ['url' => $footerUrl($cms['footer_twitter'] ?? ''), 'icon' => 'fa-brands fa-x-twitter', 'label' => 'X (Twitter)'],
        ['url' => $footerUrl($cms['footer_website']), 'icon' => 'fa-solid fa-globe', 'label' => 'Website'],
    ], fn ($item) => filled($item['url'])));
    $footerContactRows = array_values(array_filter([
        ['icon' => 'fa-solid fa-location-dot', 'value' => $cms['contact_address']],
        ['icon' => 'fa-solid fa-phone', 'value' => $cms['contact_phone'], 'link' => 'tel:'.preg_replace('/[^0-9+]/', '', $cms['contact_phone'])],
        ['icon' => 'fa-solid fa-envelope', 'value' => $cms['contact_email'], 'link' => 'mailto:'.$cms['contact_email']],
        ['icon' => 'fa-solid fa-clock', 'value' => $cms['contact_hours']],
    ], fn ($item) => filled($item['value'])));
    $footerCopyright = trim(strtr($cms['footer_copyright'] ?: ($lmIsKhmer ? '© {year} {business}។ រក្សាសិទ្ធិគ្រប់យ៉ាង។' : '© {year} {business}. All rights reserved.'), [
        '{year}' => date('Y'),
        '{business}' => $businessName,
    ])) ?: ($lmIsKhmer ? '© '.date('Y').' '.$businessName.'។ រក្សាសិទ្ធិគ្រប់យ៉ាង។' : '© '.date('Y').' '.$businessName);
    $footerQuickLinks = [];
    foreach ($visibleMenu as $footerKey => $footerSection) {
        $footerMenuLabel = $cms['label_'.$footerKey];
        if ($lmIsKhmer) {
            $defaultMenuMap = [
                'Home' => 'ទំព័រដើម',
                'Products Catalog' => 'កាតាឡុកទំនិញ',
                'Installment Guide' => 'របៀបស្នើសុំ',
                'Cart' => 'កន្ត្រក',
                'About Us' => 'អំពីយើង',
                'Contact' => 'ទំនាក់ទំនង',
            ];
            $footerMenuLabel = $defaultMenuMap[$footerMenuLabel] ?? $footerMenuLabel;
        }
        $footerQuickLinks[] = ['href' => '#'.$footerKey, 'label' => $footerMenuLabel, 'icon' => 'fa-solid fa-chevron-right'];
    }
    if (! $customerUser && $customerPortalEnabled && $cms['catalog']) {
        $footerQuickLinks[] = ['href' => route('loan-management.public.register'), 'label' => $lmText('Request Installment', 'ស្នើសុំបង់រំលស់'), 'icon' => 'fa-solid fa-chevron-right'];
    }
    $footerAccounts = [];
    if ($customerUser) {
        $footerAccounts[] = ['href' => route('loan-management.public.customer-dashboard'), 'label' => $lmText('My Dashboard', 'ផ្ទាំងគ្រប់គ្រងរបស់ខ្ញុំ'), 'icon' => 'fa-solid fa-gauge-high'];
    }
    if ($adminUser) {
        $footerAccounts[] = ['href' => route('loan-management.dashboard'), 'label' => $lmText('Admin Dashboard', 'ផ្ទាំងគ្រប់គ្រងរដ្ឋបាល'), 'icon' => 'fa-solid fa-gauge-high'];
    } else {
        $footerAccounts[] = [
            'href' => route('login'),
            'label' => $lmText('Admin Login', 'ចូលប្រព័ន្ធគ្រប់គ្រង'),
            'icon' => 'fa-solid fa-user-shield',
            'confirm' => $customerUser ? $lmText('Are you sure you want to log out first to access Admin Login?', 'តើអ្នកប្រាកដជាចង់ចាកចេញដើម្បីចូលប្រព័ន្ធគ្រប់គ្រង?') : null,
        ];
    }
    if ($customerPortalEnabled) $footerAccounts[] = [
        'href' => route('loan-management.public.customer-login'),
        'label' => $customerUser ? $lmText('Switch Customer Account', 'ប្តូរគណនីអតិថិជន') : $lmText('Customer Portal', 'ផតថលអតិថិជន'),
        'icon' => 'fa-solid fa-id-card',
        'confirm' => $adminUser ? $lmText('You are currently signed in as Administrator ('.($adminUser->name ?? 'Admin').'). Are you sure you want to log out first to switch to Customer Portal?', 'អ្នកកំពុងចូលជាអ្នកគ្រប់គ្រង ('.($adminUser->name ?? 'Admin').')។ តើអ្នកប្រាកដជាចង់ចាកចេញដើម្បីប្តូរទៅកាន់ផតថលអតិថិជន?') : null,
    ];
    $footerAccounts[] = [
        'href' => route('loan-management.products'),
        'label' => $lmText('Installment Products Setup', 'រៀបចំទំនិញបង់រំលស់'),
        'icon' => 'fa-solid fa-boxes-stacked',
    ];
    $footerAccounts[] = [
        'href' => route('loan-management.public.home'),
        'label' => $lmText('Back to Homepage', 'ត្រឡប់ទៅទំព័រដើម'),
        'icon' => 'fa-solid fa-house',
    ];
    $footerSignedIn = $customerUser ?? $adminUser;
    $catalogCount = count($products ?? []);
@endphp
<!doctype html>
<html lang="{{ $lmIsKhmer ? 'km' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $businessName }}</title>
    @if($lmIsKhmer)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&display=swap">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --public-primary:#b9570b; --brand-orange:#d76b17; --ink:#231f20; --muted:#6d6d72; --line:#e5e5e7; --panel:#fff; --soft:#f6f6f7; }
        * { box-sizing:border-box; letter-spacing:0; }
        html { scroll-behavior:smooth; scroll-padding-top:90px; }
        body { margin:0; font:14px/1.5 Arial,sans-serif; color:var(--ink); background:var(--soft); }
        a { color:inherit; text-decoration:none; }
        button,input,select { font:inherit; }
        button,a,input,select { -webkit-tap-highlight-color:transparent; }
        button { cursor:pointer; }
        :focus-visible { outline:3px solid var(--brand-orange); outline-offset:3px; }
        svg { width:18px; height:18px; flex-shrink:0; }
        .announcement { background:#202020; color:#ddd; font-size:12px; padding:9px 0; }
        .announcement-inner,.nav-inner,.section-inner,.footer-inner,.brand-strip-inner { max-width:1240px; width:calc(100% - 48px); margin:auto; }
        .announcement-inner { display:flex; justify-content:space-between; gap:16px; }
        .announcement-links { display:flex; flex-wrap:wrap; gap:16px; }
        .announcement i { color:#f69b64; margin-right:6px; }
        .site-nav { position:sticky; top:0; z-index:50; background:#fff; border-bottom:1px solid var(--line); }
        .nav-inner { display:flex; align-items:center; justify-content:space-between; min-height:78px; gap:22px; }
        .brand { display:flex; align-items:center; gap:10px; min-width:0; }
        .brand-logo { width:48px; height:44px; background:#202020; color:#fff; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800; flex-shrink:0; overflow:hidden; }
        .brand-logo img { width:100%; height:100%; object-fit:contain; background:#fff; }
        .brand-text { font-size:18px; font-weight:800; overflow-wrap:anywhere; }
        .brand-text small { display:block; color:var(--muted); font-size:10px; font-weight:400; }
        .menu { display:flex; gap:22px; align-items:center; font-weight:600; font-size:13px; }
        .menu a { padding:12px 0; overflow-wrap:anywhere; }
        .menu a.active,.menu a:hover { color:var(--public-primary); }
        .nav-actions { display:flex; align-items:center; gap:10px; }
        .button,.button-outline,.cart-apply { display:inline-flex; justify-content:center; align-items:center; gap:8px; min-height:42px; padding:10px 20px; border-radius:6px; background:var(--public-primary); color:#fff; border:1px solid var(--public-primary); font-weight:700; text-align:center; }
        .button:hover,.cart-apply:hover { background:#934407; }
        .button-outline { background:#fff; color:#303030; border-color:var(--line); }
        .button-outline:hover { border-color:var(--public-primary); color:var(--public-primary); }
        .menu-toggle { display:none; width:40px; height:40px; border:1px solid var(--line); background:#fff; border-radius:4px; }
        .hero { position:relative; color:#fff; background:#222; }
        .hero-image { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center 55%; }
        .hero::after { content:''; position:absolute; inset:0; background:rgba(20,18,19,.56); }
        .hero-inner { position:relative; z-index:1; max-width:1240px; width:calc(100% - 48px); margin:auto; min-height:440px; padding:52px 0; display:flex; align-items:center; }
        .hero-copy { max-width:670px; }
        .eyebrow { display:inline-flex; align-items:center; gap:10px; font-size:12px; font-weight:700; color:#ffbd92; }
        .eyebrow::before { content:''; width:28px; height:2px; background:currentColor; }
        .hero h1 { margin:16px 0 20px; font-size:52px; line-height:1.08; overflow-wrap:anywhere; }
        .hero .subtitle { font-size:18px; margin:0 0 12px; max-width:600px; }
        .hero .subtitle:first-of-type { font-size:24px; font-weight:600; }
        .hero .body-copy { color:#f2f2f2; font-size:15px; max-width:540px; margin:0 0 22px; }
        .hero-cta { display:flex; flex-wrap:wrap; gap:12px; margin-top:28px; }
        .hero-cta a { min-height:48px; }
        .hero .button-outline { background:rgba(0,0,0,.25); color:#fff; border-color:#fff; }
        .brand-strip { padding:28px 0; background:#fff; border-bottom:1px solid var(--line); }
        .brand-strip-inner { display:grid; grid-template-columns:repeat(8,minmax(0,1fr)); align-items:center; gap:12px; }
        .brand-strip small { color:var(--muted); font-size:11px; }
        .brand-strip span { font-size:18px; font-weight:800; color:#363235; }
        .brand-strip h2 { margin:0 0 20px; text-align:center; font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase; }
        .brand-partner { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; min-width:0; min-height:64px; padding:8px; color:#555; text-decoration:none; overflow-wrap:anywhere; text-align:center; }
        a.brand-partner:hover span { color:var(--public-primary); }
        .brand-partner img { width:120px; max-width:100%; height:44px; object-fit:contain; }
        .assessment { background:#f6f6f7; padding:28px 0; border-bottom:1px solid var(--line); border-top:3px solid var(--brand-orange); }
        .assessment h2 { font-size:21px; margin:0 0 18px; color:var(--ink); }
        .assessment-grid { display:grid; grid-template-columns:minmax(180px,2fr) repeat(3,minmax(120px,1fr)); gap:16px; align-items:end; }
        .assessment label { display:block; font-weight:600; font-size:12px; margin-bottom:6px; }
        .assessment input,.assessment select { width:100%; height:42px; border:1px solid var(--line); border-radius:6px; padding:8px; background:#fff; }
        .assessment-output { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-top:18px; }
        .assessment-output strong { font-size:28px; color:var(--public-primary); margin-left:8px; }
        .assessment p { color:var(--muted); margin:12px 0 0; }
        .footer-policy-btn { background:transparent; border:0; color:inherit; padding:0; font:inherit; text-decoration:underline; }
        .product-stock { font-size:11px; color:#18733d; margin-bottom:6px; }
        .product-stock.unavailable { color:#b42318; }
        @media(max-width:700px) { .assessment-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        .section { padding:48px 0; }
        .section.alt { background:#fff; }
        .section-head { margin-bottom:24px; }
        .section h2 { margin:0 0 10px; font-size:28px; line-height:1.25; }
        .section-head p { color:var(--muted); margin:0; }
        .feature-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:30px; }
        .feature { padding:16px 0; min-width:0; border-top:1px solid var(--line); }
        .feature-icon { width:40px; height:40px; display:grid; place-items:center; background:#fff1e6; color:var(--public-primary); font-weight:700; border-radius:6px; margin-bottom:14px; }
        #experience { background:#231f20; color:#fff; }
        #experience .section-head p,#experience .feature span { color:#c6c4c5; }
        #experience .feature { border-color:#4b4548; }
        #experience .feature-icon { background:transparent; color:#ffbd92; font-size:24px; }
        #about { border-top:1px solid var(--line); }
        #contact { border-top:1px solid var(--line); }
        .feature strong { display:block; font-size:18px; margin-bottom:6px; }
        .feature span { display:block; color:var(--muted); }
        .shop-inner { display:grid; grid-template-columns:minmax(0,1fr) 330px; gap:28px; align-items:start; }
        .shop-inner > div { min-width:0; }
        .shop-inner.without-cart { grid-template-columns:minmax(0,1fr); }
        .contact-details { display:flex; flex-wrap:wrap; gap:20px; }
        .contact-details p,.section p { white-space:pre-line; overflow-wrap:anywhere; }
        .catalog-filter-bar { margin-bottom:20px; }
        .catalog-search-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
        .search-input-box { position:relative; flex:1; min-width:180px; }
        .search-input-box > svg { position:absolute; left:12px; top:13px; color:var(--muted); }
        .search-input-box input { width:100%; height:44px; border:1px solid var(--line); border-radius:6px; padding:0 36px; background:#fff; }
        .search-clear-btn { position:absolute; right:8px; top:7px; border:0; background:none; font-size:22px; height:30px; }
        .sort-select-box select { height:44px; max-width:100%; border:1px solid var(--line); border-radius:6px; background:#fff; padding:0 10px; }
        .category-chips-scroll { display:flex; gap:8px; flex-wrap:wrap; }
        .category-chip { min-height:34px; border:1px solid var(--line); background:#fff; padding:6px 12px; border-radius:4px; font-size:12px; }
        .category-chip.active { background:var(--public-primary); border-color:var(--public-primary); color:#fff; }
        .chip-count { margin-left:6px; font-weight:700; }
        .product-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
        .product-card { display:flex; flex-direction:column; min-width:0; overflow:hidden; background:#fff; border:1px solid var(--line); border-radius:8px; transition:border-color .2s,box-shadow .2s; }
        .product-card:hover { border-color:#d9bca5; box-shadow:0 6px 20px #231f2010; }
        .product-image-wrap { position:relative; aspect-ratio:3/2; background:#fff; display:grid; place-items:center; border-bottom:1px solid var(--line); overflow:hidden; }
        .product-image-wrap img { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; padding:18px; }
        .product-fallback-icon { color:#9aa4b2; }
        .card-badge-top-left,.card-badge-top-right { position:absolute; top:10px; background:#fff; font-size:10px; font-weight:700; padding:4px 7px; border-radius:4px; }
        .card-badge-top-left { left:10px; color:#5a5558; }
        .card-badge-top-right { right:10px; color:#77411c; }
        .product-body { display:flex; flex-direction:column; gap:8px; padding:18px; flex:1; }
        .product-brand-tag { color:var(--muted); font-size:11px; }
        .product-title { margin:0; font-size:17px; line-height:1.4; min-height:48px; overflow-wrap:anywhere; }
        .product-sku { color:var(--muted); font-size:11px; }
        .product-price-row { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px; margin-top:auto; padding-top:8px; }
        .price-cash { font-size:24px; font-weight:800; color:var(--ink); }
        .price-monthly-tag { font-size:11px; color:var(--public-primary); }
        .product-actions-grid { display:flex; gap:8px; margin-top:8px; }
        .cart-btn,.apply-btn { display:flex; align-items:center; justify-content:center; gap:6px; border:1px solid #252525; background:#252525; color:#fff; min-height:40px; border-radius:6px; font-size:12px; padding:8px 10px; font-weight:600; }
        .cart-btn { flex:1; }
        .apply-btn { background:#fff; color:#252525; }
        .cart-btn:hover { background:var(--public-primary); border-color:var(--public-primary); }
        .cart-panel { position:sticky; top:100px; padding:22px; background:#fff; border:1px solid var(--line); border-top:3px solid var(--brand-orange); border-radius:8px; }
        .cart-panel-head { display:flex; align-items:center; justify-content:space-between; gap:8px; padding-bottom:14px; border-bottom:1px solid var(--line); }
        .cart-panel h2 { font-size:18px; margin:0; }
        .cart-count-pill { font-size:11px; color:#a24415; white-space:nowrap; }
        .cart-items { padding:12px 0; }
        .cart-empty { font-size:13px; color:var(--muted); padding:14px 0; }
        .cart-item { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 0; border-bottom:1px solid var(--line); }
        .cart-item > div:first-child { min-width:0; }
        .cart-item strong { display:block; font-size:12px; overflow-wrap:anywhere; }
        .cart-item span { display:block; font-size:12px; margin-top:4px; }
        .qty-row { display:flex; align-items:center; gap:8px; flex-shrink:0; }
        .qty-row button { width:28px; height:28px; background:#fff; border:1px solid var(--line); border-radius:4px; }
        .cart-total { display:flex; justify-content:space-between; gap:10px; border-top:1px solid var(--line); padding:18px 0; font-weight:700; }
        .cart-apply { width:100%; font-size:13px; }
        .footer { background:#202020; color:#b8bec8; padding:40px 0 24px; }
        .footer-grid { display:grid; grid-template-columns:minmax(0,1.5fr) repeat(3,minmax(0,1fr)); gap:32px; margin-bottom:26px; align-items:start; }
        .footer-group { min-width:0; }
        .footer-group-title { margin:0 0 12px; font-size:16px; font-weight:800; color:#fff; }
        .footer-group-toggle { display:flex; width:100%; align-items:center; justify-content:space-between; gap:10px; margin:0 0 12px; padding:0; background:none; border:0; color:#fff; font-size:16px; font-weight:800; text-align:left; }
        .footer-group-toggle i { display:none; font-size:13px; color:#ffc39e; transition:transform .2s ease; }
        .footer p { font-size:13px; max-width:400px; margin:0 0 12px; white-space:pre-line; overflow-wrap:anywhere; }
        .footer-brand { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
        .footer-brand-logo { width:46px; height:42px; flex-shrink:0; display:grid; place-items:center; border-radius:4px; background:#303030; color:#fff; font-size:18px; font-weight:800; overflow:hidden; }
        .footer-brand-logo img { width:100%; height:100%; object-fit:contain; background:#fff; }
        .footer-brand strong { display:block; color:#fff; font-size:15px; overflow-wrap:anywhere; }
        .footer-brand small { display:block; color:#8b929c; font-size:11px; margin-top:2px; }
        .footer-links { display:flex; flex-direction:column; gap:9px; font-size:13px; }
        .footer-links a { display:inline-flex; align-items:center; gap:8px; width:fit-content; }
        .footer-links i { width:14px; color:#6f7681; }
        .footer-contact-list { display:flex; flex-direction:column; gap:11px; font-size:13px; }
        .footer-contact-list > div { display:flex; gap:10px; align-items:flex-start; }
        .footer-contact-list i { width:15px; flex-shrink:0; margin-top:2px; color:#ffc39e; }
        .footer-contact-list span { min-width:0; overflow-wrap:anywhere; white-space:pre-line; }
        .footer-social-title { margin:18px 0 10px; font-size:12px; font-weight:800; color:#8b929c; letter-spacing:0; text-transform:uppercase; }
        .footer-social { display:flex; flex-wrap:wrap; gap:9px; }
        .footer-social a { width:36px; height:36px; display:grid; place-items:center; border:1px solid #414141; border-radius:6px; background:#2a2a2a; color:#d3d7dd; }
        .footer-social a:hover { color:#fff; border-color:#ffc39e; }
        .footer-cta { display:flex; flex-wrap:wrap; gap:10px; margin-top:4px; }
        .footer-cta a { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:40px; padding:9px 16px; border:1px solid #4b4b4b; border-radius:6px; background:#2a2a2a; color:#fff; font-size:12px; font-weight:700; }
        .footer-cta a.primary { background:var(--public-primary); border-color:var(--public-primary); }
        .footer a:hover { color:#ffc39e; }
        .footer-note { border-top:1px solid #414141; border-bottom:1px solid #414141; padding:13px 0; margin-bottom:16px; font-size:12px; color:#8b929c; }
        .footer-bottom { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; font-size:12px; }
        .footer-meta { display:flex; flex-wrap:wrap; gap:6px 18px; }
        .footer-meta a { color:#8b929c; }
        .footer-top-btn { position:fixed; right:20px; bottom:20px; z-index:60; width:44px; height:44px; display:grid; place-items:center; border:1px solid #414141; border-radius:50%; background:#202020; color:#fff; box-shadow:0 10px 24px rgba(0,0,0,.28); opacity:0; visibility:hidden; transform:translateY(10px); transition:opacity .2s ease, transform .2s ease, visibility .2s ease; }
        .footer-top-btn.is-visible { opacity:1; visibility:visible; transform:translateY(0); }
        .footer-top-btn:hover { background:var(--public-primary); border-color:var(--public-primary); }
        @media (max-width:980px) {
            .footer-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
        }
        @media (max-width:700px) {
            .footer-grid { grid-template-columns:minmax(0,1fr); gap:0; }
            .footer-group { border-top:1px solid #343434; padding:4px 0; }
            .footer-group:first-child { border-top:0; }
            .footer-group-toggle { margin:0; padding:14px 0; }
            .footer-group-toggle i { display:block; }
            .footer-group.is-collapsed .footer-group-toggle i { transform:rotate(-90deg); }
            .footer-group.is-collapsed .footer-group-body { display:none; }
            .footer-group-body { padding:2px 0 16px; }
            .footer-top-btn { right:14px; bottom:14px; width:40px; height:40px; }
        }
        .user-dropdown-wrapper { position:relative; }
        .user-profile-btn { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid var(--line); border-radius:6px; min-height:42px; padding:6px 10px; }
        .user-profile-name { max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; }
        .user-avatar-img,.user-avatar-badge { width:28px; height:28px; object-fit:cover; border-radius:50%; display:grid; place-items:center; background:#fce8da; }
        .user-dropdown-menu { display:none; position:absolute; right:0; top:calc(100% + 8px); width:270px; max-width:calc(100vw - 24px); background:#fff; border:1px solid var(--line); border-radius:8px; box-shadow:0 10px 28px #0002; overflow:hidden; z-index:80; }
        .user-dropdown-wrapper.open .user-dropdown-menu { display:block; }
        .dropdown-header-box { display:flex; gap:10px; padding:16px; align-items:center; }
        .dropdown-avatar-circle,.dropdown-avatar-circle-fallback { width:36px; height:36px; border-radius:50%; background:#fce8da; object-fit:cover; display:grid; place-items:center; flex-shrink:0; }
        .dropdown-user-name { font-weight:700; overflow-wrap:anywhere; }
        .dropdown-user-sub { color:var(--muted); font-size:11px; overflow-wrap:anywhere; }
        .dropdown-item { display:flex; gap:10px; align-items:center; width:100%; padding:12px 16px; background:#fff; border:0; font-size:12px; text-align:left; }
        .dropdown-item:hover { background:#f4f5f7; }
        .dropdown-item.danger { color:#ba2929; }
        .dropdown-divider { border-top:1px solid var(--line); }
        .mobile-bottom-nav { display:none; }
        @media (max-width:1050px) {
            .menu { gap:12px; font-size:12px; }
            .nav-inner { gap:12px; }
            .brand-text { font-size:15px; }
            .shop-inner { grid-template-columns:minmax(0,1fr) 290px; gap:18px; }
        }
        @media (max-width:1100px) {
            .brand-strip-inner { grid-template-columns:repeat(4,minmax(0,1fr)); }
            .announcement-inner,.nav-inner,.section-inner,.footer-inner,.brand-strip-inner,.hero-inner { width:calc(100% - 32px); }
            .menu-toggle { display:block; }
            .menu { display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border-bottom:1px solid var(--line); padding:12px 16px; flex-direction:column; align-items:stretch; gap:0; }
            .menu.is-open { display:flex; }
            .menu a { padding:12px 0; }
            .nav-inner { min-height:68px; }
            .shop-inner { grid-template-columns:1fr; }
            .cart-panel { position:static; }
            .feature-grid { gap:16px; }
            .hero h1 { font-size:36px; }
            .hero-inner { min-height:350px; padding:38px 0; }
        }
        @media (max-width:520px) {
            .brand-strip-inner { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
            .brand-partner { min-height:48px; }
            .hero .subtitle:first-of-type { font-size:20px; }
            .hero-cta { margin-top:20px; }
            .announcement-inner { flex-direction:column; gap:4px; }
            .announcement { font-size:11px; }
            .brand-logo { width:34px; height:36px; font-size:15px; }
            .brand-text { font-size:13px; max-width:125px; }
            .brand-text small { font-size:8px; }
            .nav-inner { gap:6px; }
            .nav-actions { gap:6px; }
            .nav-actions > .button,.nav-actions > .button-outline { padding:7px 9px; min-height:36px; font-size:11px; }
            .menu-toggle { width:34px; height:36px; }
            .user-profile-name { max-width:60px; font-size:11px; }
            .hero h1 { font-size:32px; }
            .hero .subtitle { font-size:17px; }
            .hero .body-copy { font-size:13px; }
            .hero .button,.hero .button-outline { padding:9px 14px; font-size:12px; }
            .section { padding:28px 0; }
            .section h2 { font-size:23px; }
            .feature-grid { grid-template-columns:1fr; gap:0; }
            .feature { display:grid; grid-template-columns:40px 1fr; gap:4px 14px; border-bottom:1px solid var(--line); }
            .feature-icon { grid-row:span 2; margin:0; }
            .feature strong { margin:0; font-size:16px; }
            .feature span { font-size:13px; }
            .product-grid { gap:12px; }
            .product-body { padding:12px; }
            .product-title { font-size:14px; min-height:42px; }
            .product-image-wrap img { padding:16px; }
            .product-price-row { align-items:start; flex-direction:column; }
            .price-cash { font-size:18px; }
            .product-actions-grid { flex-direction:column; }
            .card-badge-top-left { left:6px; top:6px; }
            .card-badge-top-right { right:6px; top:auto; bottom:6px; }
            .footer-grid { grid-template-columns:minmax(0,1fr); }
        }
        @media (prefers-reduced-motion:reduce) { html { scroll-behavior:auto; } }

        /* Language switcher styling */
        .cms-lang-switch {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 2px 3px;
            height: 34px;
            box-sizing: border-box;
            user-select: none;
            gap: 2px;
            flex-shrink: 0;
        }
        .cms-lang-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 28px;
            padding: 0 10px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-decoration: none !important;
            border-radius: 999px;
            background: transparent;
            cursor: pointer;
            line-height: 1;
            transition: all .15s ease;
        }
        .cms-lang-btn:hover {
            color: #0f172a;
        }
        .cms-lang-btn.active {
            background: #ffffff;
            color: var(--public-primary, #b9570b);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            font-weight: 800;
        }
        .cms-lang-divider {
            width: 1px;
            height: 14px;
            background: #cbd5e1;
            display: inline-block;
        }
        .announcement-lang {
            margin-left: 10px;
            padding-left: 10px;
            border-left: 1px solid rgba(255,255,255,0.25);
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .announcement-lang a {
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-weight: 600;
        }
        .announcement-lang a.active {
            color: #ffffff;
            font-weight: 800;
            text-decoration: underline;
        }
        .menu-mobile-lang {
            display: none;
            padding: 12px 0 6px;
            margin-top: 8px;
            border-top: 1px solid var(--line);
            font-size: 13px;
            color: var(--muted);
            align-items: center;
            gap: 8px;
        }
        .menu-mobile-lang a {
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
        }
        .menu-mobile-lang a.active {
            color: var(--public-primary);
            font-weight: 800;
            text-decoration: underline;
        }
        @media (max-width: 1100px) {
            .menu-mobile-lang { display: flex; }
        }
        @media (max-width: 520px) {
            .cms-lang-switch { height: 32px; padding: 2px; }
            .cms-lang-btn { height: 26px; padding: 0 7px; font-size: 11px; }
        }
        /* Khmer font support */
        html[lang="km"],
        body.lm-lang-km {
            font-family: "Kantumruy Pro", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }
    </style>
</head>
<body @class(['lm-lang-km' => $lmIsKhmer])>
    <main class="public-shell">
        @if($cms['announcement'])
        <div class="announcement"><div class="announcement-inner">
            <span>{{ $businessName }} &middot; {{ $cms['announcement_text'] }} @if($cms['contact_address']) &middot; {{ $cms['contact_address'] }} @endif @if($cms['contact_phone']) &middot; <a href="tel:{{ preg_replace('/[^0-9+]/', '', $cms['contact_phone']) }}">{{ $cms['contact_phone'] }}</a> @endif</span>
            <div class="announcement-links">@if($customerPortalEnabled)<a href="{{ route('loan-management.public.customer-login') }}">{{ $lmText('Customer Portal', 'ផតថលអតិថិជន') }}</a>@endif<a href="{{ route('login') }}">{{ $lmText('Admin Login', 'ចូលប្រព័ន្ធគ្រប់គ្រង') }}</a><span class="announcement-lang"><a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" data-lang="en" class="{{ !$lmIsKhmer ? 'active' : '' }}">EN</a> &middot; <a href="{{ request()->fullUrlWithQuery(['lang' => 'km']) }}" data-lang="km" class="{{ $lmIsKhmer ? 'active' : '' }}">ខ្មែរ</a></span></div>
        </div></div>
        @endif
        <header class="site-nav">
            <div class="nav-inner">
                <a class="brand" href="{{ route('loan-management.public.home') }}">
                    <span class="brand-logo">
                        @if($businessLogoUrl)
                            <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">
                        @else
                            {{ strtoupper(mb_substr($businessName, 0, 1)) }}
                        @endif
                    </span>
                    <span class="brand-text">{{ $businessName }}<small>{{ $cms['tagline'] }}</small></span>
                </a>
                @if(!empty($visibleMenu))<button type="button" class="menu-toggle" id="publicMenuToggle" aria-label="{{ $lmText('Toggle navigation', 'បើក/បិទ ម៉ឺនុយ') }}" aria-controls="publicMainMenu" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>@endif
                <nav class="menu" id="publicMainMenu" aria-label="{{ $lmText('Main menu', 'ម៉ឺនុយមេ') }}">
                    @foreach($visibleMenu as $key => $section)
                        @php
                            $menuLabel = $cms['label_'.$key] ?? '';
                            if ($lmIsKhmer) {
                                $defaultMenuMap = [
                                    'Home' => 'ទំព័រដើម',
                                    'Products Catalog' => 'កាតាឡុកទំនិញ',
                                    'Installment Guide' => 'របៀបស្នើសុំ',
                                    'Cart' => 'កន្ត្រក',
                                    'About Us' => 'អំពីយើង',
                                    'Contact' => 'ទំនាក់ទំនង',
                                ];
                                $menuLabel = $defaultMenuMap[$menuLabel] ?? $menuLabel;
                            }
                        @endphp
                        <a href="#{{ $key }}" data-section-link="{{ $key }}" @class(['active' => $loop->first])>{{ $menuLabel }}</a>
                    @endforeach
                    <div class="menu-mobile-lang">
                        <span>{{ $lmText('Language:', 'ភាសា៖') }}</span>
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" data-lang="en" class="{{ !$lmIsKhmer ? 'active' : '' }}">English</a>
                        &middot;
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'km']) }}" data-lang="km" class="{{ $lmIsKhmer ? 'active' : '' }}">ភាសាខ្មែរ</a>
                    </div>
                </nav>
                <div class="nav-actions">
                    {{-- Language Switcher near Customer Login --}}
                    <div class="cms-lang-switch" role="group" aria-label="{{ $lmText('Language selector', 'ជ្រើសរើសភាសា') }}" title="{{ $lmText('Switch language', 'ប្តូរភាសា') }}">
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                           data-lang="en"
                           @class(['cms-lang-btn', 'active' => !$lmIsKhmer])
                           aria-pressed="{{ !$lmIsKhmer ? 'true' : 'false' }}"
                           title="English">
                            EN
                        </a>
                        <span class="cms-lang-divider"></span>
                        <a href="{{ request()->fullUrlWithQuery(['lang' => 'km']) }}"
                           data-lang="km"
                           @class(['cms-lang-btn', 'active' => $lmIsKhmer])
                           aria-pressed="{{ $lmIsKhmer ? 'true' : 'false' }}"
                           title="ភាសាខ្មែរ">
                            ខ្មែរ
                        </a>
                    </div>

                    @if($adminUser)
                        <div class="user-dropdown-wrapper" id="adminDropdownWrapper">
                            <button type="button" class="user-profile-btn admin-profile-btn" id="adminProfileToggle" aria-expanded="false">
                                @if($adminPhotoUrl)
                                    <img src="{{ $adminPhotoUrl }}" class="user-avatar-img" alt="Admin">
                                @else
                                    <span class="user-avatar-badge admin-avatar-badge">⚡</span>
                                @endif
                                <span class="user-profile-name">{{ $adminUser->name ?? $adminUser->username ?? 'Admin' }}</span>
                                <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="user-dropdown-menu" id="adminDropdownMenu">
                                <div class="dropdown-header-box">
                                    @if($adminPhotoUrl)
                                        <img src="{{ $adminPhotoUrl }}" class="dropdown-avatar-circle" alt="Admin">
                                    @else
                                        <div class="dropdown-avatar-circle-fallback admin-avatar-circle-fallback">⚡</div>
                                    @endif
                                    <div style="min-width: 0;">
                                        <div class="dropdown-user-name">{{ $adminUser->name ?? $adminUser->username ?? 'Administrator' }}</div>
                                        <div class="dropdown-user-sub">{{ $adminUser->email ?? $lmText('Admin & Staff Portal', 'ផតថលរដ្ឋបាល និងបុគ្គលិក') }}</div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="{{ route('loan-management.dashboard') }}" class="dropdown-item">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    {{ $lmText('Admin Dashboard', 'ផ្ទាំងគ្រប់គ្រងរដ្ឋបាល') }}
                                </a>
                                <a href="{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" class="dropdown-item" onclick="return confirm('{{ $lmText('Are you sure you want to log out and switch to Customer Portal?', 'តើអ្នកប្រាកដជាចង់ចាកចេញ ហើយប្តូរទៅកាន់ផតថលអតិថិជន?') }}');" title="{{ $lmText('Switch or login as customer', 'ប្តូរ ឬចូលជាអតិថិជន') }}">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    {{ $lmText('Switch to Customer', 'ប្តូរទៅអតិថិជន') }}
                                </a>
                                <a href="{{ Route::has('logout') ? route('logout') : url('/logout') }}?redirect={{ urlencode(route('login')) }}" class="dropdown-item" onclick="return confirm('{{ $lmText('Are you sure you want to log out and switch admin account?', 'តើអ្នកប្រាកដជាចង់ចាកចេញ ហើយប្តូរគណនីរដ្ឋបាល?') }}');" title="{{ $lmText('Switch or login as another admin user', 'ប្តូរ ឬចូលជាគណនីរដ្ឋបាលផ្សេង') }}">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    {{ $lmText('Switch / Other Admin', 'ប្តូរ / គណនីរដ្ឋបាលផ្សេង') }}
                                </a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}" style="margin: 0;" onsubmit="return confirm('{{ $lmText('Are you sure you want to log out?', 'តើអ្នកប្រាកដជាចង់ចាកចេញមែនទេ?') }}');">
                                    @csrf
                                    <button type="submit" class="dropdown-item danger">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        {{ $lmText('Logout Admin', 'ចាកចេញពីរដ្ឋបាល') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if($customerUser)
                        <div class="user-dropdown-wrapper" id="customerDropdownWrapper">
                            <button type="button" class="user-profile-btn" id="customerProfileToggle" aria-expanded="false">
                                @if($customerPhotoUrl)
                                    <img src="{{ $customerPhotoUrl }}" class="user-avatar-img" alt="{{ $customerUser->name ?: 'Customer' }}">
                                @else
                                    <span class="user-avatar-badge">{{ strtoupper(mb_substr($customerUser->name ?: 'C', 0, 1)) }}</span>
                                @endif
                                <span class="user-profile-name">{{ $customerUser->name ?: $lmText('Customer', 'អតិថិជន') }}</span>
                                <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="user-dropdown-menu" id="customerDropdownMenu">
                                <div class="dropdown-header-box">
                                    @if($customerPhotoUrl)
                                        <img src="{{ $customerPhotoUrl }}" class="dropdown-avatar-circle" alt="{{ $customerUser->name ?: 'Customer' }}">
                                    @else
                                        <div class="dropdown-avatar-circle-fallback">{{ strtoupper(mb_substr($customerUser->name ?: 'C', 0, 1)) }}</div>
                                    @endif
                                    <div style="min-width: 0;">
                                        <div class="dropdown-user-name">{{ $customerUser->name ?: $lmText('Customer', 'អតិថិជន') }}</div>
                                        <div class="dropdown-user-sub">{{ $customerUser->phone ?: $customerUser->username }}</div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="{{ route('loan-management.public.customer-dashboard') }}" class="dropdown-item">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    {{ $lmText('My Dashboard', 'ផ្ទាំងគ្រប់គ្រងរបស់ខ្ញុំ') }}
                                </a>
                                <a href="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('loan-management.public.customer-login')) }}" class="dropdown-item" onclick="return confirm('{{ $lmText('Are you sure you want to log out and switch to another account?', 'តើអ្នកប្រាកដជាចង់ចាកចេញ ហើយប្តូរទៅកាន់គណនីផ្សេង?') }}');" title="{{ $lmText('Switch or add another customer account', 'ប្តូរ ឬបន្ថែមគណនីអតិថិជនផ្សេង') }}">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    {{ $lmText('Switch Account', 'ប្តូរគណនី') }}
                                </a>
                                @if(! $adminUser)
                                    <a href="{{ route('loan-management.public.customer-logout') }}?redirect={{ urlencode(route('login')) }}" class="dropdown-item" onclick="return confirm('{{ $lmText('Are you sure you want to log out and go to Admin Login?', 'តើអ្នកប្រាកដជាចង់ចាកចេញ ហើយទៅកាន់ការចូលប្រព័ន្ធគ្រប់គ្រង?') }}');">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                        {{ $lmText('Admin Login', 'ចូលប្រព័ន្ធគ្រប់គ្រង') }}
                                    </a>
                                @endif
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('loan-management.public.customer-logout') }}" style="margin: 0;" onsubmit="return confirm('{{ $lmText('Are you sure you want to log out?', 'តើអ្នកប្រាកដជាចង់ចាកចេញមែនទេ?') }}');">
                                    @csrf
                                    <button type="submit" class="dropdown-item danger">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        {{ $lmText('Logout Customer', 'ចាកចេញពីអតិថិជន') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif(! $adminUser && $customerPortalEnabled)
                        <a class="button-outline" href="{{ route('loan-management.public.customer-login') }}">{{ $lmText('Login', 'ចូលប្រើ') }}</a>
                        <a class="button" href="{{ route('loan-management.public.register') }}">{{ $lmText('Apply Now', 'ស្នើសុំឥឡូវនេះ') }}</a>
                    @endif
                </div>
            </div>
        </header>

        @if($cms['hero'])
        <section class="hero" id="home">
            <img class="hero-image" src="{{ route('loan-management.public.home-image') }}" alt="Computer and technology showroom" fetchpriority="high">
            <div class="hero-inner">
                <div class="hero-copy">
                    <span class="eyebrow">{{ $cms['hero_eyebrow'] }}</span>
                    <h1>{{ $businessName }}</h1>
                    <p class="subtitle">{{ $headline }}</p>
                    @if($subtitle)
                        <p class="subtitle">{{ $subtitle }}</p>
                    @endif
                    @if($body)
                        <p class="body-copy">{{ $body }}</p>
                    @endif
                    <div class="hero-cta">
                        @if($customerUser)
                            <a class="button" href="{{ route('loan-management.public.customer-dashboard') }}">{{ $lmText('Go to My Dashboard', 'ទៅកាន់ផ្ទាំងគ្រប់គ្រង') }}</a>
                            @if($cms['catalog'])<a class="button-outline" href="#products">{{ $lmText('Shop Products', 'ទិញទំនិញ') }}</a>@endif
                        @elseif($adminUser)
                            <a class="button" href="{{ route('loan-management.dashboard') }}">{{ $lmText('Open Admin Dashboard', 'បើកផ្ទាំងគ្រប់គ្រងរដ្ឋបាល') }}</a>
                            <a class="button-outline" href="{{ route('loan-management.public.customer-login') }}" onclick="return confirm('{{ $lmText('You are currently signed in as Administrator. Are you sure you want to log out first to switch to Customer Portal?', 'អ្នកកំពុងចូលជាអ្នកគ្រប់គ្រង។ តើអ្នកប្រាកដជាចង់ចាកចេញដើម្បីប្តូរទៅកាន់ផតថលអតិថិជនមែនទេ?') }}');">{{ $lmText('Customer Login / Switch', 'ចូលគណនីអតិថិជន / ប្តូរ') }}</a>
                        @else
                            @if($cms['catalog'])<a class="button" href="#products">{{ $lmText('Shop Products', 'ទិញទំនិញ') }}</a>@endif
                            @if($customerPortalEnabled)<a class="button-outline" href="{{ route('loan-management.public.register') }}">{{ $lmText('Apply Now', 'ស្នើសុំឥឡូវនេះ') }}</a>@endif
                        @endif
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if($cms['assessment'] && $cms['catalog'] && !empty($products))
        <section class="assessment" aria-labelledby="assessmentTitle"><div class="section-inner">
            <h2 id="assessmentTitle">{{ $cms['assessment_title'] }}</h2>
            <div class="assessment-grid">
                <div><label for="assessmentProduct">{{ $lmText('Selected Item', 'ទំនិញដែលបានជ្រើស') }}</label><select id="assessmentProduct">@foreach($products as $product)<option value="{{ $loop->index }}">{{ $product['name'] }}</option>@endforeach</select></div>
                <div><label for="assessmentPrice">{{ $lmText('Product Price', 'តម្លៃទំនិញ') }}</label><input id="assessmentPrice" readonly></div>
                <div><label for="assessmentMonths">{{ $lmText('Months', 'ចំនួនខែ') }}</label><input id="assessmentMonths" type="number" min="1" max="120" value="12" required></div>
                <div><label for="assessmentDownPayment">{{ $lmText('Down Payment', 'ប្រាក់កក់មុន ($)') }}</label><input id="assessmentDownPayment" type="number" min="0" step="0.01" value="0" required></div>
            </div>
            <div class="assessment-output"><div>{{ $lmText('Estimated Monthly:', 'ការបង់ប្រចាំខែប៉ាន់ស្មាន:') }} <strong id="assessmentMonthly"></strong></div>@if($customerPortalEnabled)<button type="button" class="button" id="assessmentApply"><i class="fa-solid fa-sliders" aria-hidden="true"></i> {{ $lmText('Configure Installment', 'កំណត់រំលស់') }}</button>@endif</div>
            <p>{{ $cms['assessment_note'] }}</p>
        </div></section>
        @endif

        @if($cms['brands'] && !empty($partnerBrands))
            <section class="brand-strip" aria-labelledby="partnerBrandsTitle">
                <h2 id="partnerBrandsTitle">{{ $cms['brands_title'] }}</h2>
                <div class="brand-strip-inner">
                    @foreach($partnerBrands as $partner)
                        @if($partner['website_url'])
                            <a class="brand-partner" href="{{ $partner['website_url'] }}" target="_blank" rel="noopener noreferrer">
                        @else
                            <div class="brand-partner">
                        @endif
                            @if($partner['logo_url'])<img src="{{ $partner['logo_url'] }}" alt="" loading="lazy" onerror="this.hidden=true">@endif
                            <span>{{ $partner['name'] }}</span>
                        @if($partner['website_url'])</a>@else</div>@endif
                    @endforeach
                </div>
            </section>
        @endif

        @if($cms['guide'])
        <section class="section alt" id="how">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <h2>{{ $cms['guide_title'] }}</h2>
                        <p>{{ $cms['guide_description'] }}</p>
                    </div>
                </div>
                <div class="feature-grid">
                    @foreach([1, 2, 3] as $step)
                        <div class="feature"><div class="feature-icon">{{ $step }}</div><strong>{{ $cms['step_'.$step.'_title'] }}</strong><span>{{ $cms['step_'.$step.'_body'] }}</span></div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        @if($cms['experience'])
        <section class="section" id="experience"><div class="section-inner">
            <div class="section-head"><div><h2>{{ $cms['experience_title'] }}</h2><p>{{ $cms['experience_description'] }}</p></div></div>
            <div class="feature-grid">@foreach([1,2,3] as $step)<div class="feature"><div class="feature-icon"><i class="fa-solid {{ [1 => 'fa-laptop', 2 => 'fa-wallet', 3 => 'fa-headset'][$step] }}" aria-hidden="true"></i></div><strong>{{ $cms['experience_'.$step.'_title'] }}</strong><span>{{ $cms['experience_'.$step.'_body'] }}</span></div>@endforeach</div>
        </div></section>
        @endif

        @if($cms['catalog'])
        <section class="section" id="products">
            <div @class(['section-inner', 'shop-inner', 'without-cart' => !$cms['cart']])>
                <div>
                    <div class="section-head">
                        <div>
                            <h2>{{ $cms['catalog_title'] }}</h2>
                            <p>{{ $cms['catalog_description'] }}</p>
                        </div>
                    </div>

                    {{-- Search & Category Filter Toolbar --}}
                    <div class="catalog-filter-bar">
                        <div class="catalog-search-row">
                            <div class="search-input-box">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="catalogSearchInput" aria-label="{{ $lmText('Search products', 'ស្វែងរកទំនិញ') }}" placeholder="{{ $lmText('Search products, brand, SKU...', 'ស្វែងរកទំនិញ ម៉ាក ឬលេខកូដ...') }}" autocomplete="off">
                                <button type="button" class="search-clear-btn" id="searchClearBtn" style="display: none;" title="{{ $lmText('Clear search', 'សម្អាតការស្វែងរក') }}">&times;</button>
                            </div>
                            <div class="sort-select-box">
                                <select id="catalogSortSelect" aria-label="{{ $lmText('Sort products', 'តម្រៀបទំនិញ') }}">
                                    <option value="default">{{ $lmText('Featured / Newest', 'ទំនិញពិសេស / ថ្មីបំផុត') }}</option>
                                    <option value="price_low">{{ $lmText('Price: Low to High', 'តម្លៃ: ទាបទៅខ្ពស់') }}</option>
                                    <option value="price_high">{{ $lmText('Price: High to Low', 'តម្លៃ: ខ្ពស់ទៅទាប') }}</option>
                                    <option value="name_asc">{{ $lmText('Name: A to Z', 'ឈ្មោះ: A ដល់ Z') }}</option>
                                </select>
                            </div>
                        </div>

                        {{-- Category Filter Chips --}}
                        <div class="category-chips-scroll" id="categoryChipsContainer">
                            <button type="button" class="category-chip active" data-category="all">
                                {{ $lmText('All Products', 'ទំនិញទាំងអស់') }} <span class="chip-count" id="totalCountPill">{{ count($products) }}</span>
                            </button>
                            @foreach($categories ?? [] as $cat)
                                @php
                                    $catCount = collect($products)->where('category', $cat)->count();
                                @endphp
                                <button type="button" class="category-chip" data-category="{{ strtolower($cat) }}">
                                    {{ $cat }} <span class="chip-count">{{ $catCount }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Product Grid --}}
                    @if(!empty($products))
                        <div class="product-grid" id="productsCatalogGrid">
                            @foreach($products as $product)
                                @php
                                    $pPrice = (float) ($product['price'] ?? 0);
                                    $pName = (string) ($product['name'] ?? 'Product');
                                    $pSku = (string) ($product['sku'] ?? '');
                                    $pBrand = (string) ($product['brand'] ?? '');
                                    $pCat = (string) ($product['category'] ?? 'General');
                                    $pMinDp = (float) ($product['min_down_payment_percent'] ?? 0);
                                    $estMonthly = $pPrice > 0 ? round($pPrice / 12, 2) : 0;
                                @endphp
                                <article class="product-card"
                                    data-name="{{ strtolower($pName) }}"
                                    data-sku="{{ strtolower($pSku) }}"
                                    data-brand="{{ strtolower($pBrand) }}"
                                    data-model="{{ strtolower($product['model'] ?? '') }}"
                                    data-category="{{ strtolower($pCat) }}"
                                    data-price="{{ $pPrice }}"
                                    data-id="{{ $product['id'] ?? $loop->index }}">

                                    <div class="product-image-wrap">
                                        @if(!empty($product['image_url']))
                                            <img src="{{ $product['image_url'] }}" alt="{{ $pName }}" loading="lazy">
                                        @else
                                            <div class="product-fallback-icon">
                                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                            </div>
                                        @endif

                                        @if($pCat && strtolower($pCat) !== 'general')
                                            <span class="card-badge-top-left">{{ $pCat }}</span>
                                        @endif

                                        @if($pMinDp > 0)
                                            <span class="card-badge-top-right">{{ $lmText('Min ' . $pMinDp . '% DP', 'កក់ទាបបំផុត ' . $pMinDp . '%') }}</span>
                                        @else
                                            <span class="card-badge-top-right">{{ $lmText('0% Down Payment', 'កក់មុន 0%') }}</span>
                                        @endif
                                    </div>

                                    <div class="product-body">
                                        <div @class(['product-stock', 'unavailable' => ($product['qty_available'] ?? 1) <= 0])>{{ ($product['qty_available'] ?? 1) > 0 ? $lmText('In Stock', 'មានក្នុងស្តុក') : $lmText('Stock subject to staff confirmation', 'ស្តុកត្រូវបញ្ជាក់ជាមួយបុគ្គលិក') }}</div>
                                        @if($pBrand)
                                            <div class="product-brand-tag">{{ $pBrand }}</div>
                                        @endif
                                        <h3 class="product-title">{{ $pName }}</h3>
                                        <div class="product-sku">{{ $pSku ? 'SKU: ' . $pSku : $lmText('Installment Eligible', 'អាចបង់រំលស់បាន') }}</div>

                                        <div class="product-price-row">
                                            <div class="price-cash">${{ number_format($pPrice, 2) }}</div>
                                            @if($estMonthly > 0)
                                                <div class="price-monthly-tag">{{ $lmText('Est.', 'ប៉ាន់ស្មាន') }} ${{ number_format($estMonthly, 2) }}/{{ $lmText('mo', 'ខែ') }}</div>
                                            @endif
                                        </div>

                                        <div class="product-actions-grid">
                                            @if($cms['cart'])<button type="button" class="cart-btn" data-product='@json($product)'>
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                                {{ $lmText('Add to Cart', 'ដាក់ក្នុងកន្ត្រក') }}
                                            </button>@endif
                                            @if($customerUser)
                                                <a href="{{ route('loan-management.public.customer-loan-request', ['product_id' => $product['id']]) }}" class="apply-btn" title="{{ $lmText('Direct Installment Request', 'ស្នើសុំបង់រំលស់ផ្ទាល់') }}">
                                                    {{ $lmText('Apply', 'ស្នើរំលស់') }}
                                                </a>
                                            @elseif($customerPortalEnabled)
                                                <a href="{{ route('loan-management.public.register', ['product_id' => $product['id']]) }}" data-installment-product="{{ json_encode($product) }}" class="apply-btn" title="{{ $lmText('Direct Installment Request', 'ស្នើសុំបង់រំលស់ផ្ទាល់') }}">
                                                    {{ $lmText('Apply', 'ស្នើរំលស់') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div id="noProductsFoundMsg" style="display: none; padding: 48px 20px; text-align: center; background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px; margin-top: 10px;">
                            <div style="font-size: 38px; color: #94a3b8; margin-bottom: 8px;">🔍</div>
                            <h4 style="margin: 0 0 4px; font-weight: 800; color: #0f172a;">{{ $lmText('No matching products found', 'រកមិនឃើញទំនិញដែលត្រូវគ្នាទេ') }}</h4>
                            <p style="margin: 0 0 14px; color: #64748b; font-size: 13px;">{{ $lmText('Try searching with another keyword or resetting the category filter.', 'សូមសាកល្បងពាក្យគន្លឹះផ្សេង ឬកំណត់តម្រងឡើងវិញ') }}</p>
                            <button type="button" class="button-outline" onclick="resetAllFilters()" style="min-height: 36px; padding: 0 14px; font-size: 13px;">{{ $lmText('Reset Filters', 'កំណត់តម្រងឡើងវិញ') }}</button>
                        </div>
                    @else
                        <div class="feature">
                            <strong>{{ $lmText('No installment products available', 'មិនទាន់មានទំនិញរំលស់ទេ') }}</strong>
                            <span>{{ $lmText('Please check back soon for new products.', 'សូមចូលពិនិត្យមើលឡើងវិញនៅពេលក្រោយ') }}</span>
                        </div>
                    @endif
                </div>

                @if($cms['cart'])<aside class="cart-panel" id="cart">
                    <div class="cart-panel-head">
                        <h2>{{ $lmText('Installment Cart', 'កន្ត្រករំលស់') }}</h2>
                        <span class="cart-count-pill" id="cartCountPill">{{ $lmText('0 items', '0 មុខ') }}</span>
                    </div>
                    <div class="cart-items" id="cartItems"></div>
                    <div class="cart-total">
                        <span>{{ $lmText('Subtotal', 'សរុបរង') }}</span><span id="cartSubtotal">$0.00</span>
                    </div>
                    <div class="cart-total">
                        <span>{{ $lmText('Estimated Total', 'សរុបប៉ាន់ស្មាន') }}</span>
                        <span id="cartTotal">$0.00</span>
                    </div>
                    <p id="cartLimitMessage" role="status" hidden style="color:#b42318;"></p>
                    @if($customerUser)
                        <a class="cart-apply" href="{{ route('loan-management.public.customer-loan-request') }}" id="cartApply">
                            {{ $lmText('Apply Installment', 'ស្នើសុំរំលស់') }}
                        </a>
                    @elseif($customerPortalEnabled)
                        <a class="cart-apply" href="{{ route('loan-management.public.register') }}" id="cartApply">
                            {{ $lmText('Apply Installment', 'ស្នើសុំរំលស់') }}
                        </a>
                    @endif
                </aside>@endif
            </div>
        </section>
        @endif

        @if($cms['about'])
        <section class="section alt" id="about"><div class="section-inner">
            <h2>{{ $cms['about_title'] }}</h2>
            <p>{{ $body ?: $subtitle }}</p>
        </div></section>
        @endif
        @if($cms['contact'])
            <section class="section" id="contact"><div class="section-inner">
                <h2>{{ $cms['contact_title'] }}</h2>
                <div class="contact-details">
                    @if($cms['contact_phone'])<p><i class="fa-solid fa-phone" aria-hidden="true"></i> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $cms['contact_phone']) }}">{{ $cms['contact_phone'] }}</a></p>@endif
                    @if($cms['contact_email'])<p><i class="fa-solid fa-envelope" aria-hidden="true"></i> <a href="mailto:{{ $cms['contact_email'] }}">{{ $cms['contact_email'] }}</a></p>@endif
                    @if($cms['contact_address'])<p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $cms['contact_address'] }}</p>@endif
                    @if($cms['contact_hours'])<p><i class="fa-solid fa-clock" aria-hidden="true"></i> {{ $cms['contact_hours'] }}</p>@endif
                </div>
                @if($customerPortalEnabled)<a class="button-outline" href="{{ route('loan-management.public.customer-login') }}">{{ $lmText('Customer Portal', 'ផតថលអតិថិជន') }}</a>@endif
            </div></section>
        @endif

        @if($cms['footer'])<footer class="footer" id="publicFooter">
            <div class="footer-inner">
                <div class="footer-grid">
                    <section class="footer-group footer-brand-group">
                        <div class="footer-brand">
                            <span class="footer-brand-logo">
                                @if($businessLogoUrl)
                                    <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }}">
                                @else
                                    {{ strtoupper(mb_substr($businessName, 0, 1)) }}
                                @endif
                            </span>
                            <span>
                                <strong>{{ $businessName }}</strong>
                                <small>{{ $cms['tagline'] }}</small>
                            </span>
                        </div>
                        <div class="footer-group-body">
                            @if($cms['footer_text'])<p>{{ $cms['footer_text'] }}</p>@endif
                            <div class="footer-cta">
                                @if($customerUser)
                                    <a class="primary" href="{{ route('loan-management.public.customer-dashboard') }}"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> {{ $lmText('My Dashboard', 'ផ្ទាំងគ្រប់គ្រង') }}</a>
                                @elseif($adminUser)
                                    <a class="primary" href="{{ route('loan-management.dashboard') }}"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> {{ $lmText('Admin Dashboard', 'ផ្ទាំងគ្រប់គ្រងរដ្ឋបាល') }}</a>
                                @elseif($customerPortalEnabled)
                                    <a class="primary" href="{{ route('loan-management.public.register') }}"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> {{ $lmText('Apply Now', 'ស្នើសុំឥឡូវនេះ') }}</a>
                                    <a href="{{ route('loan-management.public.customer-login') }}"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> {{ $lmText('Login', 'ចូលគណនី') }}</a>
                                @endif
                            </div>
                            @if($footerSignedIn)
                                <p style="margin-top:12px; color:#8b929c;"><i class="fa-solid fa-circle-user" aria-hidden="true"></i> {{ $lmText('Signed in as', 'បានចូលដោយឈ្មោះ') }} {{ $customerUser ? $customerUser->name : ($adminUser->name ?? $adminUser->username ?? 'Administrator') }}</p>
                            @endif
                            @if($cms['footer_show_social'] && $footerSocials)
                                <div class="footer-social-title">{{ $cms['footer_social_title'] }}</div>
                                <div class="footer-social">
                                    @foreach($footerSocials as $footerSocial)
                                        <a href="{{ $footerSocial['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $footerSocial['label'] }}" aria-label="{{ $footerSocial['label'] }}"><i class="{{ $footerSocial['icon'] }}" aria-hidden="true"></i></a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>

                    @if($cms['footer_show_links'] && $footerQuickLinks)
                        <section class="footer-group">
                            <button type="button" class="footer-group-toggle" aria-expanded="true" aria-controls="footerLinksBody">
                                <span>{{ $cms['footer_links_title'] }}</span>
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="footer-group-body" id="footerLinksBody">
                                <div class="footer-links">
                                    @foreach($footerQuickLinks as $footerLink)
                                        <a href="{{ $footerLink['href'] }}"><i class="{{ $footerLink['icon'] }}" aria-hidden="true"></i> {{ $footerLink['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif

                    @if($cms['footer_show_accounts'] && $footerAccounts)
                        <section class="footer-group">
                            <button type="button" class="footer-group-toggle" aria-expanded="true" aria-controls="footerAccountsBody">
                                <span>{{ $cms['footer_accounts_title'] }}</span>
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="footer-group-body" id="footerAccountsBody">
                                <div class="footer-links">
                                    @foreach($footerAccounts as $footerAccount)
                                        <a href="{{ $footerAccount['href'] }}"@if(!empty($footerAccount['confirm'])) onclick="return confirm(@json($footerAccount['confirm']));"@endif><i class="{{ $footerAccount['icon'] }}" aria-hidden="true"></i> {{ $footerAccount['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif

                    @if($cms['footer_show_contact'] && $footerContactRows)
                        <section class="footer-group">
                            <button type="button" class="footer-group-toggle" aria-expanded="true" aria-controls="footerContactBody">
                                <span>{{ $cms['footer_contact_title'] }}</span>
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="footer-group-body" id="footerContactBody">
                                <div class="footer-contact-list">
                                    @foreach($footerContactRows as $footerContact)
                                        <div>
                                            <i class="{{ $footerContact['icon'] }}" aria-hidden="true"></i>
                                            @if(!empty($footerContact['link']))<a href="{{ $footerContact['link'] }}"><span>{{ $footerContact['value'] }}</span></a>
                                            @else<span>{{ $footerContact['value'] }}</span>@endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif
                </div>

                @if($cms['footer_show_note'] && $cms['footer_note'])<div class="footer-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ $cms['footer_note'] }}</div>@endif
                @if($cms['privacy'] && $cms['privacy_body'])<p><button type="button" class="footer-policy-btn" id="privacyOpen">{{ $cms['privacy_title'] }}</button></p>@endif

                <div class="footer-bottom">
                    <div>{{ $footerCopyright }}</div>
                    <div class="footer-meta">
                        @if($cms['hero'])<a href="#home" data-section-link="home"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> {{ $lmText('Back to top', 'ត្រឡប់ទៅលើ') }}</a>@endif
                        @if($cms['cart'] && $catalogCount)<span>{{ $catalogCount }} {{ $lmText($catalogCount === 1 ? 'product available' : 'products available', 'មុខទំនិញមានលក់') }}</span>@endif
                        <span>{{ $businessName }} &middot; Powered by rvstechsolution.com</span>
                    </div>
                </div>
            </div>
        </footer>@endif

        <button type="button" class="footer-top-btn" id="backToTopBtn" aria-label="{{ $lmText('Back to top', 'ត្រឡប់ទៅលើ') }}" title="{{ $lmText('Back to top', 'ត្រឡប់ទៅលើ') }}">
            <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
        </button>

    </main>

    @include('loanmanagement::public.partials.installment_request_modal')
    @include('loanmanagement::public.partials.customer_login_modal')
    @if($cms['privacy'] && $cms['privacy_body'])
        <dialog class="installment-modal" id="privacyModal" aria-labelledby="privacyTitle"><h2 id="privacyTitle">{{ $cms['privacy_title'] }}</h2><p style="white-space:pre-wrap;overflow-wrap:anywhere;">{{ $cms['privacy_body'] }}</p><form method="dialog"><button class="button" type="submit">{{ $lmText('Close', 'បិទ') }}</button></form></dialog>
    @endif

    <script>
        (function () {
            var menuToggle = document.getElementById('publicMenuToggle');
            var mainMenu = document.getElementById('publicMainMenu');
            function closeMenu() {
                if (!menuToggle) return;
                mainMenu.classList.remove('is-open');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
            if (menuToggle) menuToggle.addEventListener('click', function () {
                var open = mainMenu.classList.toggle('is-open');
                menuToggle.setAttribute('aria-expanded', String(open));
            });
            mainMenu.addEventListener('click', function (event) {
                if (event.target.closest('a')) closeMenu();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeMenu();
            });
            var cartKey = 'loan_public_installment_cart';
            var liveCatalog = @json($products ?? []);
            var cart = [];
            var isKhmer = {{ $lmIsKhmer ? 'true' : 'false' }};
            var itemsBox = document.getElementById('cartItems');
            var totalBox = document.getElementById('cartTotal');
            var countPill = document.getElementById('cartCountPill');
            var apply = document.getElementById('cartApply');
            var mobCartBadge = document.getElementById('mobileCartBadge');
            var isCustomerLoggedIn = {{ $customerUser ? 'true' : 'false' }};
            var applyBaseUrl = isCustomerLoggedIn
                ? '{{ route('loan-management.public.customer-loan-request') }}'
                : '{{ route('loan-management.public.register') }}';

            function money(value) {
                return '$' + Number(value || 0).toFixed(2);
            }

            function save() {
                try { localStorage.setItem(cartKey, JSON.stringify(cart)); } catch (e) {}
            }

            function load() {
                try {
                    cart = JSON.parse(localStorage.getItem(cartKey) || '[]') || [];
                    if (!Array.isArray(cart)) cart = [];
                    cart = cart.filter(function (item) { return item && typeof item === 'object' && Number.isInteger(Number(item.id)) && Number(item.id) > 0 && Number.isFinite(Number(item.price)) && Number(item.price) >= 0 && Number.isInteger(Number(item.qty)) && Number(item.qty) > 0 && Number(item.qty) <= 99; }).slice(0,20);
                    cart = cart.map(function (item) {
                        var product = liveCatalog.find(function (product) { return Number(product.id) === Number(item.id) && Number(product.variation_id || 0) === Number(item.variation_id || 0); });
                        return product ? Object.assign({}, product, {qty:Number(item.qty)}) : null;
                    }).filter(Boolean);
                } catch (e) {
                    cart = [];
                }
            }

            function renderCart() {
                if (!itemsBox) return;
                var limitNotice = document.getElementById('cartLimitMessage');
                if (limitNotice) limitNotice.hidden = true;
                var total = 0;
                var totalItems = 0;
                itemsBox.innerHTML = '';
                if (!cart.length) {
                    itemsBox.innerHTML = '<div class="cart-empty">' + (isKhmer ? 'មិនទាន់មានទំនិញត្រូវបានជ្រើសរើសនៅឡើយទេ។' : 'No products selected yet.') + '</div>';
                }

                cart.forEach(function (item, index) {
                    var qty = Number(item.qty || 1);
                    var price = Number(item.price || 0);
                    total += price * qty;
                    totalItems += qty;

                    var row = document.createElement('div');
                    row.className = 'cart-item';
                    row.innerHTML = '<div><strong></strong><span></span></div><div class="qty-row"><button type="button" data-action="minus" data-index="' + index + '">-</button><span>' + qty + '</span><button type="button" data-action="plus" data-index="' + index + '">+</button></div>';
                    row.querySelector('strong').textContent = item.name || 'Product';
                    row.querySelector('span').textContent = money(price * qty);
                    itemsBox.appendChild(row);
                });

                totalBox.textContent = money(total);
                document.getElementById('cartSubtotal').textContent = money(total);
                if (countPill) countPill.textContent = totalItems + (isKhmer ? ' មុខ' : (totalItems === 1 ? ' item' : ' items'));
                if (mobCartBadge) {
                    mobCartBadge.textContent = totalItems;
                    mobCartBadge.style.display = totalItems > 0 ? 'inline-block' : 'none';
                }
                if (apply) apply.href = applyBaseUrl + (cart.length ? '?cart=1' : '');
            }

            // Add to Cart Buttons
            document.querySelectorAll('.cart-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    var product = JSON.parse(button.getAttribute('data-product') || '{}');
                    var key = String(product.id || product.product_id || product.name);
                    var existing = cart.find(function (item) { return String(item.id || item.product_id || item.name) === key; });
                    if (existing) {
                        if (existing.qty >= 99) { showCartLimit(isKhmer ? 'អតិបរមា 99 ឯកតាក្នុងមួយទំនិញ។' : 'Maximum 99 units per product.'); return; }
                        existing.qty = Number(existing.qty || 1) + 1;
                    } else {
                        if (cart.length >= 20) { showCartLimit(isKhmer ? 'អតិបរមា 20 មុខទំនិញខុសៗគ្នាក្នុងមួយសំណើ។' : 'Maximum 20 different products per request.'); return; }
                        product.qty = 1;
                        cart.push(product);
                    }
                    save();
                    renderCart();

                    // Button feedback animation
                    var originalHtml = button.innerHTML;
                    button.style.background = '#16a34a';
                    button.innerHTML = isKhmer ? '✓ បានបញ្ចូល!' : '✓ Added!';
                    setTimeout(function() {
                        button.style.background = '';
                        button.innerHTML = originalHtml;
                    }, 1200);
                });
            });

            // Cart Quantity Adjustment
            if (itemsBox) itemsBox.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-action]');
                if (!button) return;
                var index = Number(button.getAttribute('data-index'));
                if (!cart[index]) return;
                if (button.getAttribute('data-action') === 'plus') {
                    if (cart[index].qty >= 99) { showCartLimit(isKhmer ? 'អតិបរមា 99 ឯកតាក្នុងមួយទំនិញ។' : 'Maximum 99 units per product.'); return; }
                    cart[index].qty = Number(cart[index].qty || 1) + 1;
                } else {
                    cart[index].qty = Number(cart[index].qty || 1) - 1;
                    if (cart[index].qty <= 0) cart.splice(index, 1);
                }
                save();
                renderCart();
            });

            load();
            save();
            renderCart();

            function showCartLimit(message) {
                var notice = document.getElementById('cartLimitMessage');
                if (notice) { notice.textContent = message; notice.hidden = false; }
            }
            var assessmentProducts = @json($products ?? []);
            var assessmentSelect = document.getElementById('assessmentProduct');
            var assessmentMonths = document.getElementById('assessmentMonths');
            var assessmentDown = document.getElementById('assessmentDownPayment');
            function updateAssessment() {
                if (!assessmentSelect) return;
                var product = assessmentProducts[Number(assessmentSelect.value)];
                var price = Number(product.price || 0);
                assessmentDown.max = price;
                document.getElementById('assessmentPrice').value = money(price);
                var valid = assessmentMonths.checkValidity() && assessmentDown.checkValidity();
                document.getElementById('assessmentMonthly').textContent = valid ? money((price - Number(assessmentDown.value)) / Number(assessmentMonths.value)) + (isKhmer ? ' / ខែ' : ' / mo') : '--';
            }
            if (assessmentSelect) {
                [assessmentSelect, assessmentMonths, assessmentDown].forEach(function (input) { input.addEventListener('input', updateAssessment); });
                var configure = document.getElementById('assessmentApply');
                if (configure) configure.addEventListener('click', function () {
                    if (!assessmentMonths.reportValidity() || !assessmentDown.reportValidity()) return;
                    window.openInstallmentRequest([Object.assign({}, assessmentProducts[Number(assessmentSelect.value)], {qty:1})], false, {months:assessmentMonths.value, downPayment:assessmentDown.value});
                });
                updateAssessment();
            }
            var privacyOpen = document.getElementById('privacyOpen');
            if (privacyOpen) privacyOpen.addEventListener('click', function () { document.getElementById('privacyModal').showModal(); });

            var registrationPath = new URL(@json(route('loan-management.public.register')), window.location.href).pathname;
            document.addEventListener('click', function (event) {
                var link = event.target.closest('a[href]');
                if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
                var url = new URL(link.href, window.location.href);
                if (url.origin !== window.location.origin || url.pathname !== registrationPath) return;
                event.preventDefault();
                closeMenu();
                var product = link.getAttribute('data-installment-product');
                window.openInstallmentRequest(product ? [Object.assign(JSON.parse(product), {qty: 1})] : cart, !product);
            });
            document.addEventListener('installment-request-submitted', function (event) {
                if (event.detail.clearCart) {
                    cart = [];
                    save();
                    renderCart();
                }
            });
            var requestParams = new URLSearchParams(window.location.search);
            if (requestParams.get('apply') === '1' || @json((bool) old('installment_request'))) {
                var initialProducts = @json($products ?? []);
                var requestedProduct = initialProducts.find(function (product) { return String(product.id) === requestParams.get('product_id'); });
                var oldItems = @json(old('installment_items'));
                var requestedItems = requestedProduct ? [Object.assign({}, requestedProduct, {qty: 1})] : cart;
                if (oldItems) {
                    try {
                        var parsedItems = JSON.parse(oldItems);
                        if (Array.isArray(parsedItems)) requestedItems = parsedItems.map(function (item) {
                            var product = initialProducts.find(function (product) { return Number(product.id) === Number(item.id) && Number(product.variation_id || 0) === Number(item.variation_id || 0); });
                            return Object.assign({}, product || item, {qty:item.qty});
                        });
                    } catch (error) {}
                }
                window.openInstallmentRequest(requestedItems, !requestedProduct);
            }

            var sectionLinks = Array.from(document.querySelectorAll('[data-section-link]'));
            var trackedSections = sectionLinks
                .map(function (link) {
                    var id = link.getAttribute('data-section-link');
                    return { id: id, link: link, section: document.getElementById(id) };
                })
                .filter(function (item) { return !!item.section; });

            function setActiveSection(id) {
                sectionLinks.forEach(function (link) {
                    var active = link.getAttribute('data-section-link') === id;
                    link.classList.toggle('active', active);
                    if (active) link.setAttribute('aria-current', 'location');
                    else link.removeAttribute('aria-current');
                });
            }

            if (trackedSections.length) {
                // Nested sticky panels are navigation targets, not page-section boundaries.
                var pageSections = trackedSections.filter(function (item) {
                    return !trackedSections.some(function (other) {
                        return other !== item && other.section.contains(item.section);
                    });
                }).sort(function (a, b) {
                    return a.section.compareDocumentPosition(b.section) & 4 ? -1 : 1;
                });
                var selectedTarget = null;
                var header = document.querySelector('.site-nav');

                function updateActiveSection() {
                    if (selectedTarget) {
                        setActiveSection(selectedTarget);
                        return;
                    }
                    var activeId = pageSections[0].id;
                    var offset = header.getBoundingClientRect().height + 14;
                    pageSections.forEach(function (item) {
                        if (item.section.getBoundingClientRect().top <= offset) activeId = item.id;
                    });
                    setActiveSection(activeId);
                }

                function selectTarget(hash) {
                    var item = trackedSections.find(function (item) { return '#' + item.id === hash; });
                    selectedTarget = item ? item.id : null;
                    updateActiveSection();
                }

                document.querySelector('.public-shell').addEventListener('click', function (event) {
                    var link = event.target.closest('a[href^="#"]');
                    if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) selectTarget(link.getAttribute('href'));
                });
                window.addEventListener('hashchange', function () { selectTarget(window.location.hash); });
                window.addEventListener('scroll', updateActiveSection, { passive: true });
                ['wheel', 'touchmove'].forEach(function (type) {
                    window.addEventListener(type, function () { selectedTarget = null; }, { passive: true });
                });
                window.addEventListener('keydown', function (event) {
                    if (event.target.closest('input, textarea, select, button, [contenteditable]')) return;
                    if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(event.key)) selectedTarget = null;
                });
                window.addEventListener('resize', function () {
                    document.documentElement.style.scrollPaddingTop = (header.getBoundingClientRect().height + 12) + 'px';
                    updateActiveSection();
                }, { passive: true });
                document.documentElement.style.scrollPaddingTop = (header.getBoundingClientRect().height + 12) + 'px';
                selectTarget(window.location.hash);
            }

            // Category & Search Engine
            var currentCategory = 'all';
            var searchInput = document.getElementById('catalogSearchInput');
            var searchClearBtn = document.getElementById('searchClearBtn');
            var sortSelect = document.getElementById('catalogSortSelect');
            var productGrid = document.getElementById('productsCatalogGrid');
            var noProductsMsg = document.getElementById('noProductsFoundMsg');

            function applyFilter() {
                var query = (searchInput ? searchInput.value : '').toLowerCase().trim();
                if (searchClearBtn) {
                    searchClearBtn.style.display = query.length > 0 ? 'block' : 'none';
                }

                var cards = document.querySelectorAll('#productsCatalogGrid .product-card');
                var visibleCount = 0;

                cards.forEach(function (card) {
                    var cCat = (card.getAttribute('data-category') || '').toLowerCase();
                    var cName = (card.getAttribute('data-name') || '').toLowerCase();
                    var cSku = (card.getAttribute('data-sku') || '').toLowerCase();
                    var cBrand = (card.getAttribute('data-brand') || '').toLowerCase();
                    var cModel = (card.getAttribute('data-model') || '').toLowerCase();

                    var matchesCat = (currentCategory === 'all' || cCat === currentCategory);
                    var matchesSearch = (query === '' || cName.includes(query) || cSku.includes(query) || cBrand.includes(query) || cModel.includes(query) || cCat.includes(query));

                    if (matchesCat && matchesSearch) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (noProductsMsg) {
                    noProductsMsg.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
                }
            }

            // Category Chip Click
            var chips = document.querySelectorAll('.category-chip');
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('active'); });
                    chip.classList.add('active');
                    currentCategory = chip.getAttribute('data-category') || 'all';
                    applyFilter();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applyFilter);
            }

            if (searchClearBtn) {
                searchClearBtn.addEventListener('click', function () {
                    searchInput.value = '';
                    applyFilter();
                    searchInput.focus();
                });
            }

            window.resetAllFilters = function () {
                if (searchInput) searchInput.value = '';
                currentCategory = 'all';
                chips.forEach(function (c) {
                    if (c.getAttribute('data-category') === 'all') c.classList.add('active');
                    else c.classList.remove('active');
                });
                if (sortSelect) sortSelect.value = 'default';
                if (sortSelect) sortSelect.dispatchEvent(new Event('change'));
                applyFilter();
            };

            // Sorting Engine
            if (sortSelect && productGrid) {
                sortSelect.addEventListener('change', function () {
                    var val = sortSelect.value;
                    var cards = Array.from(productGrid.querySelectorAll('.product-card'));

                    cards.sort(function (a, b) {
                        var priceA = parseFloat(a.getAttribute('data-price')) || 0;
                        var priceB = parseFloat(b.getAttribute('data-price')) || 0;
                        var nameA = (a.getAttribute('data-name') || '').toLowerCase();
                        var nameB = (b.getAttribute('data-name') || '').toLowerCase();
                        var idA = parseInt(a.getAttribute('data-id')) || 0;
                        var idB = parseInt(b.getAttribute('data-id')) || 0;

                        if (val === 'price_low') return priceA - priceB;
                        if (val === 'price_high') return priceB - priceA;
                        if (val === 'name_asc') return nameA.localeCompare(nameB);
                        return idB - idA; // default newest
                    });

                    cards.forEach(function (card) {
                        productGrid.appendChild(card);
                    });
                });
            }

            // Profile Dropdowns Toggle
            document.addEventListener('click', function (e) {
                var custWrapper = document.getElementById('customerDropdownWrapper');
                var custToggle = document.getElementById('customerProfileToggle');
                if (custWrapper && custToggle) {
                    if (custToggle.contains(e.target)) {
                        custWrapper.classList.toggle('open');
                        custToggle.setAttribute('aria-expanded', custWrapper.classList.contains('open') ? 'true' : 'false');
                        var admWrapper = document.getElementById('adminDropdownWrapper');
                        if (admWrapper) admWrapper.classList.remove('open');
                    } else if (!custWrapper.contains(e.target)) {
                        custWrapper.classList.remove('open');
                        custToggle.setAttribute('aria-expanded', 'false');
                    }
                }

                var admWrapper = document.getElementById('adminDropdownWrapper');
                var admToggle = document.getElementById('adminProfileToggle');
                if (admWrapper && admToggle) {
                    if (admToggle.contains(e.target)) {
                        admWrapper.classList.toggle('open');
                        admToggle.setAttribute('aria-expanded', admWrapper.classList.contains('open') ? 'true' : 'false');
                        if (custWrapper) custWrapper.classList.remove('open');
                    } else if (!admWrapper.contains(e.target)) {
                        admWrapper.classList.remove('open');
                        if (admToggle) admToggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            // Footer Accordion, Back To Top And Live Year
            (function () {
                var footer = document.getElementById('publicFooter');
                var topBtn = document.getElementById('backToTopBtn');
                var accordionBreakpoint = window.matchMedia('(max-width: 700px)');

                function syncTopButton() {
                    if (topBtn) topBtn.classList.toggle('is-visible', window.pageYOffset > 420);
                }

                if (topBtn) {
                    topBtn.addEventListener('click', function () {
                        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
                    });
                    window.addEventListener('scroll', syncTopButton, { passive: true });
                    syncTopButton();
                }

                if (!footer) return;

                var toggles = Array.from(footer.querySelectorAll('.footer-group-toggle'));

                function setGroupState(group, toggle, collapsed) {
                    group.classList.toggle('is-collapsed', collapsed);
                    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                }

                toggles.forEach(function (toggle) {
                    toggle.addEventListener('click', function () {
                        var group = toggle.closest('.footer-group');
                        setGroupState(group, toggle, !group.classList.contains('is-collapsed'));
                    });
                });

                function applyLayout() {
                    var collapsed = accordionBreakpoint.matches;
                    toggles.forEach(function (toggle) {
                        setGroupState(toggle.closest('.footer-group'), toggle, collapsed);
                    });
                }

                applyLayout();
                if (typeof accordionBreakpoint.addEventListener === 'function') {
                    accordionBreakpoint.addEventListener('change', applyLayout);
                } else if (typeof accordionBreakpoint.addListener === 'function') {
                    accordionBreakpoint.addListener(applyLayout);
                }
            })();
        })();
    </script>
    @include('loanmanagement::layouts.partials.alert_dialog')
</body>
</html>
