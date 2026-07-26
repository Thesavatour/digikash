<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Push (OS-level PWA notifications)
    |--------------------------------------------------------------------------
    |
    | VAPID keys authenticate DigiKash when sending Web Push messages to
    | browsers / installed PWAs. Generate once and keep stable:
    |   php artisan webpush:vapid
    |
    */
    'vapid' => [
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject'     => env('VAPID_SUBJECT', 'mailto:support@example.com'),
    ],

    'ttl' => (int) env('WEBPUSH_TTL', 2419200), // 28 days
];
