<?php

return [
    'disk' => env('EXPORT_DISK', 'local'),
    'queue' => env('EXPORT_QUEUE', 'exports'),
    'chunk_size' => (int) env('EXPORT_CHUNK_SIZE', 2000),
    'download_ttl_hours' => (int) env('EXPORT_DOWNLOAD_TTL_HOURS', 72),
    'cleanup_after_days' => (int) env('EXPORT_CLEANUP_AFTER_DAYS', 14),

    'types' => [
        'sales_reports' => ['label' => 'Sales Reports', 'permission' => 'sell.view'],
        'product_reports' => ['label' => 'Product Reports', 'permission' => 'product.view'],
        'loan_reports' => ['label' => 'Loan Reports', 'permission' => 'loan_management.view'],
        'repayment_reports' => ['label' => 'Repayment Reports', 'permission' => 'loan_management.view'],
        'customer_reports' => ['label' => 'Customer Reports', 'permission' => 'customer.view'],
        'imei_reports' => ['label' => 'IMEI Reports', 'permission' => 'loan_management.view'],
        'inventory_reports' => ['label' => 'Inventory Reports', 'permission' => 'product.view'],
        'transfer_reports' => ['label' => 'Transfer Reports', 'permission' => 'stock_transfer.view'],
        'audit_reports' => ['label' => 'Audit Reports', 'permission' => 'activity_log.view'],
    ],
];
