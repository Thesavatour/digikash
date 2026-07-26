<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live verification driver
    |--------------------------------------------------------------------------
    |
    | Runtime driver is resolved from admin setting `kyc_live_driver`, falling
    | back to this env value: none | builtin | didit
    |
    | builtin — in-browser camera capture (document + selfie)
    | didit   — Didit hosted verification (redirect + webhook)
    | none    — disable live step; file uploads only
    |
    */
    'live_driver' => env('KYC_LIVE_DRIVER', 'none'),

    'builtin' => [
        'require_selfie'   => (bool) env('KYC_BUILTIN_REQUIRE_SELFIE', true),
        'require_document' => (bool) env('KYC_BUILTIN_REQUIRE_DOCUMENT', true),
        // Length of the live face video recording (seconds).
        'liveness_record_seconds' => (float) env('KYC_BUILTIN_LIVENESS_RECORD', 5.0),
    ],

    'didit' => [
        'api_key'        => env('DIDIT_API_KEY'),
        'api_secret'     => env('DIDIT_API_SECRET'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
        'workflow_id'    => env('DIDIT_WORKFLOW_ID'),
        'base_url'       => env('DIDIT_BASE_URL', 'https://verification.didit.me'),
    ],

    'sumsub' => [
        'app_token'  => env('SUMSUB_APP_TOKEN'),
        'secret_key' => env('SUMSUB_SECRET_KEY'),
        'base_url'   => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),
    ],

];
