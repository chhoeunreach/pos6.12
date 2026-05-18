<?php

$broadcastDriver = env('BROADCAST_DRIVER') ?: 'null';
$pusherAppId = env('PUSHER_APP_ID');
$pusherAppKey = env('PUSHER_APP_KEY');
$pusherAppSecret = env('PUSHER_APP_SECRET');
$pusherAppCluster = env('PUSHER_APP_CLUSTER', 'mt1');

if (
    $broadcastDriver === 'pusher'
    && (! extension_loaded('curl') || empty($pusherAppId) || empty($pusherAppKey) || empty($pusherAppSecret))
) {
    $broadcastDriver = 'null';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => $broadcastDriver,

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => $pusherAppKey,
            'secret' => $pusherAppSecret,
            'app_id' => $pusherAppId,
            'options' => [
                'cluster' => $pusherAppCluster,
                'host' => env('PUSHER_HOST') ?: 'api-'.$pusherAppCluster.'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
