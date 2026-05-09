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
    'khmer_font_family' => "'Noto Sans Khmer', 'Khmer OS', 'Battambang', sans-serif",
];
