<?php

return [

    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => 'eu',
                'useTLS' => true,
            ],
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
