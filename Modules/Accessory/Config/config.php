<?php

return [
    'name' => 'Accessory',
    'module_version' => '1.0.0',
    'route_prefix' => env('ACCESSORY_ROUTE_PREFIX', 'pos/accessory'),
    'route_name_prefix' => 'accessory.',
    'database_connection' => env('ACCESSORY_DB_CONNECTION', 'accessory'),
    'excluded_main_modules' => [
        'Repair',
    ],
    'middleware' => [
        'web',
    ],
];

