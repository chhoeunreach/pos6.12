<?php

namespace Modules\LoanManagement\Services;

class CmsHomeService
{
    public static function sampleBrands(): array
    {
        return self::normalizeBrands(array_map(fn ($name) => ['name' => $name], [
            'PREDATOR', 'Microsoft', 'Lenovo', 'HIKVISION', 'ASUS', 'EPSON', 'Canon', 'TOSHIBA',
        ]));
    }

    public static function sampleDefaults(): array
    {
        return self::normalize([
            'brands_source' => 'managed',
            'brands_items' => self::sampleBrands(),
            'guide_description' => 'Select products, submit your contact details, and our staff will follow up on your pending installment request.',
            'step_1_title' => 'Choose product',
            'step_1_body' => 'Browse the catalog and add your selected products to the installment cart.',
            'step_2_title' => 'Submit request',
            'step_2_body' => 'Send your name, phone number, and contact address with your selected items.',
            'step_3_title' => 'Staff follow-up',
            'step_3_body' => 'Our staff will contact you to review your request and discuss installment terms.',
            'contact_phone' => '+855 12 345 678',
            'contact_email' => 'showroom@example.com',
            'contact_address' => 'Kampuchea Krom (128), Sangkat Teuk Laak I, Khan Tuol Kork, Phnom Penh',
            'contact_hours' => 'Monday - Saturday, 8:00 AM - 6:00 PM',
        ]);
    }
    public static function fields(): array
    {
        $fields = [];
        foreach (['announcement', 'hero', 'assessment', 'brands', 'guide', 'experience', 'catalog', 'cart', 'about', 'contact', 'privacy', 'footer'] as $section) {
            $fields[$section] = [$section === 'brands' ? 'Show Authorized Brands & Partners' : ucfirst($section).' Section', 'boolean', true];
        }
        foreach (['home' => 'Home', 'products' => 'Products Catalog', 'how' => 'Installment Guide', 'cart' => 'Cart', 'about' => 'About Us', 'contact' => 'Contact'] as $key => $label) {
            $fields['menu_'.$key] = [$label.' Menu', 'boolean', true];
            $fields['label_'.$key] = [$label.' Menu Label', 'text', $label];
        }
        return $fields + [
            'tagline' => ['Brand Tagline', 'text', 'Showroom & Installment'],
            'announcement_text' => ['Announcement Text', 'text', 'Showroom & Installment'],
            'hero_eyebrow' => ['Hero Eyebrow', 'text', 'Installment Shopping'],
            'assessment_title' => ['Assessment Title', 'text', 'Instant Loan Assessment'],
            'assessment_note' => ['Assessment Note', 'textarea', 'Estimate before interest and fees. Our staff confirms final installment terms after review.'],
            'experience_title' => ['Experience Title', 'text', 'Simple customer experience'],
            'experience_description' => ['Experience Description', 'textarea', 'Browse products, request installment service, and return to your customer account once staff has enabled access.'],
            'experience_1_title' => ['Experience 1 Title', 'text', 'Choose product'],
            'experience_1_body' => ['Experience 1 Description', 'textarea', 'Add products from the catalog to your installment cart.'],
            'experience_2_title' => ['Experience 2 Title', 'text', 'Submit request'],
            'experience_2_body' => ['Experience 2 Description', 'textarea', 'Send your contact details and selected items. Our staff will follow up.'],
            'experience_3_title' => ['Experience 3 Title', 'text', 'Track your account'],
            'experience_3_body' => ['Experience 3 Description', 'textarea', 'After staff enables your account, log in to view loan records and payment history.'],
            'privacy_title' => ['Privacy Link Title', 'text', 'Privacy Policy'],
            'privacy_body' => ['Privacy Content', 'textarea', 'Contact our showroom team for information about our privacy policy.'],
            'brands_title' => ['Section Title', 'text', 'Authorized Brands & Partners'],
            'brands_source' => ['Brand Source', 'select', 'catalog'],
            'brands_items' => ['Brands & Partners', 'collection', []],
            'guide_title' => ['Guide Title', 'text', 'Apply in a few minutes'],
            'guide_description' => ['Guide Description', 'textarea', 'Choose a product, submit your request, and our staff will follow up.'],
            'step_1_title' => ['Step 1 Title', 'text', 'Choose product'],
            'step_1_body' => ['Step 1 Description', 'textarea', 'Add products from the catalog to your installment cart.'],
            'step_2_title' => ['Step 2 Title', 'text', 'Submit request'],
            'step_2_body' => ['Step 2 Description', 'textarea', 'Send your contact details and selected items to our team.'],
            'step_3_title' => ['Step 3 Title', 'text', 'Staff follow-up'],
            'step_3_body' => ['Step 3 Description', 'textarea', 'Our staff will contact you to review your pending request.'],
            'catalog_title' => ['Catalog Title', 'text', 'Products Catalog'],
            'catalog_description' => ['Catalog Description', 'textarea', 'Find your next product and submit an installment request. All requests are subject to review.'],
            'about_title' => ['About Title', 'text', 'About Us'],
            'contact_title' => ['Contact Title', 'text', 'Contact Us'],
            'contact_phone' => ['Contact Phone', 'text', ''],
            'contact_email' => ['Contact Email', 'email', ''],
            'contact_address' => ['Contact Address', 'textarea', ''],
            'contact_hours' => ['Opening Hours', 'text', ''],
            'footer_text' => ['Footer Text', 'textarea', 'For your applications, installment account, and payment history, visit the Customer Portal.'],
            'footer_note' => ['Footer Note', 'textarea', 'All installment requests are subject to review and approval by our team.'],
            'footer_copyright' => ['Footer Copyright Text', 'text', "\u{00A9} {year} {business}. All rights reserved."],
            'footer_links_title' => ['Footer Quick Links Title', 'text', 'Quick Links'],
            'footer_accounts_title' => ['Footer Accounts Title', 'text', 'Accounts'],
            'footer_contact_title' => ['Footer Contact Title', 'text', 'Get In Touch'],
            'footer_social_title' => ['Footer Social Title', 'text', 'Follow Us'],
            'footer_facebook' => ['Footer Facebook URL', 'text', ''],
            'footer_telegram' => ['Footer Telegram URL', 'text', ''],
            'footer_tiktok' => ['Footer TikTok URL', 'text', ''],
            'footer_instagram' => ['Footer Instagram URL', 'text', ''],
            'footer_youtube' => ['Footer YouTube URL', 'text', ''],
            'footer_whatsapp' => ['Footer WhatsApp URL', 'text', ''],
            'footer_linkedin' => ['Footer LinkedIn URL', 'text', ''],
            'footer_twitter' => ['Footer X / Twitter URL', 'text', ''],
            'footer_website' => ['Footer Website URL', 'text', ''],
            'footer_show_contact' => ['Footer Show Contact Block', 'boolean', true],
            'footer_show_links' => ['Footer Show Quick Links', 'boolean', true],
            'footer_show_accounts' => ['Footer Show Accounts', 'boolean', true],
            'footer_show_social' => ['Footer Show Social Links', 'boolean', true],
            'footer_show_note' => ['Footer Show Note', 'boolean', true],
        ];
    }

    public static function normalize($values): array
    {
        $values = is_array($values) ? $values : [];
        $result = [];
        foreach (self::fields() as $key => [$label, $type, $default]) {
            $value = $values[$key] ?? $default;
            if ($type === 'collection') {
                $result[$key] = self::normalizeBrands($value);
                continue;
            }
            if ($key === 'brands_source') {
                $result[$key] = $value === 'managed' ? 'managed' : 'catalog';
                continue;
            }
            $result[$key] = $type === 'boolean' ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : mb_substr(trim(strip_tags(is_scalar($value) ? (string) $value : $default)), 0, $type === 'textarea' ? 1200 : 220);
        }
        return $result;
    }

    public static function normalizeBrands($items): array
    {
        $result = [];
        foreach (array_slice(is_array($items) ? $items : [], 0, 50) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = mb_substr(trim(strip_tags(is_scalar($item['name'] ?? null) ? (string) $item['name'] : '')), 0, 100);
            if ($name === '') {
                continue;
            }
            $result[] = [
                'name' => $name,
                'logo_url' => self::brandUrl($item['logo_url'] ?? ''),
                'website_url' => self::brandUrl($item['website_url'] ?? ''),
                'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        return $result;
    }

    protected static function brandUrl($value): string
    {
        $url = is_string($value) ? trim($value) : '';
        return strlen($url) <= 2048 && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $url : '';
    }

    public static function visibleBrands(array $cms, array $catalogBrands): array
    {
        if ($cms['brands_source'] === 'managed') {
            return array_values(array_filter($cms['brands_items'], fn ($item) => $item['enabled']));
        }
        return self::normalizeBrands(array_map(fn ($name) => ['name' => $name], $catalogBrands));
    }

    public static function groups(): array
    {
        $groups = [];
        foreach (self::fields() as $key => $field) {
            $group = match (true) {
                str_starts_with($key, 'menu_'), str_starts_with($key, 'label_') => 'Navigation',
                $key === 'brands', str_starts_with($key, 'brands_') => 'Authorized Brands & Partners',
                str_starts_with($key, 'assessment_') => 'Installment Assessment',
                str_starts_with($key, 'experience_') => 'Customer Experience',
                str_starts_with($key, 'privacy_') => 'Privacy Policy',
                str_starts_with($key, 'guide_'), str_starts_with($key, 'step_') => 'Installment Guide',
                str_starts_with($key, 'catalog_') => 'Products Catalog',
                str_starts_with($key, 'footer_social') || in_array($key, [
                    'footer_facebook', 'footer_telegram', 'footer_tiktok', 'footer_instagram',
                    'footer_youtube', 'footer_whatsapp', 'footer_linkedin', 'footer_twitter', 'footer_website', 'footer_show_social'
                ], true) => 'Follow Us & Social Media',
                str_starts_with($key, 'contact_'), str_starts_with($key, 'about_'), str_starts_with($key, 'footer_') => 'About, Contact & Footer',
                $field[1] === 'boolean' => 'Section Visibility',
                default => 'Brand & Hero',
            };
            $groups[$group][$key] = $field;
        }
        return $groups;
    }
}
