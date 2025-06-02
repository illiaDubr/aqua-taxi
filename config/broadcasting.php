<?php

return [

    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'app_id' => env('REVERB_APP_ID'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'host' => '0.0.0.0',
            'port' => env('REVERB_PORT', 6001),
            'scheme' => 'http',
            'path' => '/',
        ],

    ],

    'apps' => [
        [
            'id' => env('REVERB_APP_ID'),
            'name' => 'aqua',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'path' => '',
            'capacity' => null,
            'enable_client_messages' => false,
            'enable_statistics' => true,
        ],
    ],

    'prefix' => env('BROADCAST_PREFIX', ''),

    'middleware' => [
        'web',
    ],

];
