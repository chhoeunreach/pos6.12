<?php

return [
    'name' => 'Accessory',
    'route_prefix' => env('ACCESSORY_ROUTE_PREFIX', 'accessory-pos'),
    'route_name_prefix' => 'accessory.',
    'database_connection' => env('ACCESSORY_DB_CONNECTION', 'accessory'),
    'excluded_main_modules' => [
        'Repair',
    ],
    'middleware' => [
        'web',
    ],
];

