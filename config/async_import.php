<?php

return [
    'disk' => env('IMPORT_DISK', 'local'),
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 500),
    'queue' => env('IMPORT_QUEUE', 'imports'),
    'max_upload_kb' => (int) env('IMPORT_MAX_UPLOAD_KB', 102400),
    'cleanup_after_days' => (int) env('IMPORT_CLEANUP_AFTER_DAYS', 14),

    'types' => [
        'customers' => ['label' => 'Customer Import', 'permission' => 'customer.create'],
        'suppliers' => ['label' => 'Supplier Import', 'permission' => 'supplier.create'],
        'products' => ['label' => 'Product Import', 'permission' => 'product.create'],
        'stock' => ['label' => 'Stock Import', 'permission' => 'stock_adjustment.create'],
        'imei' => ['label' => 'IMEI Import', 'permission' => 'loan_management.import.view'],
        'loans' => ['label' => 'Loan Import', 'permission' => 'loan_management.import.view'],
        'repayments' => ['label' => 'Repayment Import', 'permission' => 'loan_management.import.view'],
    ],

    'loan_type_map' => [
        'loans' => 'loans',
        'repayments' => 'payments',
        'imei' => 'imei',
    ],
];
