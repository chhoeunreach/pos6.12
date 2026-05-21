<?php

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', env('APP_NAME', 'ultimatepos').'_horizon:'),
    'middleware' => ['web', 'auth'],
    'waits' => [
        'redis:default' => 60,
        'redis:imports' => 60,
        'redis:exports' => 60,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 10080,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'fast_termination' => false,
    'memory_limit' => 256,
    'defaults' => [
        'supervisor-imports' => [
            'connection' => 'redis',
            'queue' => ['imports'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => env('HORIZON_IMPORT_MAX_PROCESSES', 6),
            'minProcesses' => env('HORIZON_IMPORT_MIN_PROCESSES', 1),
            'tries' => 3,
            'timeout' => 900,
            'nice' => 5,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'maxProcesses' => 3,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        'supervisor-exports' => [
            'connection' => 'redis',
            'queue' => ['exports'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => env('HORIZON_EXPORT_MAX_PROCESSES', 4),
            'minProcesses' => env('HORIZON_EXPORT_MIN_PROCESSES', 1),
            'tries' => 3,
            'timeout' => 1800,
            'nice' => 5,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-imports' => [],
            'supervisor-exports' => [],
            'supervisor-default' => [],
        ],
        'local' => [
            'supervisor-imports' => ['maxProcesses' => 2],
            'supervisor-exports' => ['maxProcesses' => 2],
            'supervisor-default' => ['maxProcesses' => 1],
        ],
    ],
];
