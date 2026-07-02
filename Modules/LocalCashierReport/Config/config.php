<?php

return [
    'name' => 'LocalCashierReport',
    'module_version' => '1.0.0',
    'common_payment_method_keys' => [
        'cash',
        'custom_pay_1',
        'custom_pay_2',
        'custom_pay_3',
        'custom_pay_4',
        'card',
        'other',
    ],
    'payment_statuses' => ['paid', 'partial', 'due'],
    'qty_types' => [
        'invoice_count' => 'Invoice Count',
        'sold_quantity' => 'Sold Quantity',
    ],
    'khmer_font_family' => "'KhmerFont', 'Noto Sans Khmer', 'Khmer OS', 'Battambang', sans-serif",
    'all_sale_static_payment_columns' => [
        ['key' => 'cash',         'label' => 'Cash',     'source_methods' => ['cash']],
        ['key' => 'wing',         'label' => 'Wing',     'source_methods' => ['custom_pay_1']],
        ['key' => 'aba',          'label' => 'ABA',      'source_methods' => ['custom_pay_2']],
        ['key' => 'acleda',       'label' => 'Acleda',   'source_methods' => ['custom_pay_3']],
        ['key' => 'true',         'label' => 'TRUE',     'source_methods' => ['custom_pay_4', 'custom_pay_5']],
        ['key' => 'card',         'label' => 'Card',     'source_methods' => ['card']],
        ['key' => 'other',        'label' => 'Other',    'source_methods' => ['other']],
        ['key' => 'cut',          'label' => 'Cut',      'source_methods' => ['custom_pay_6']],
        ['key' => 'monthly',      'label' => 'បង់ប្រចាំខែ', 'source_methods' => ['custom_pay_7']],
    ],
];
