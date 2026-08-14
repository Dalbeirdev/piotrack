<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ad-platform data source (ADR-0006)
    |--------------------------------------------------------------------------
    | 'fixture' (default) returns deterministic metrics so the campaign + KPI +
    | retargeting pipeline runs and is tested without ad accounts. 'live' routes
    | each campaign to its platform driver (requires credentials + INTG OAuth).
    */
    'driver' => env('ADVERTISING_DRIVER', 'fixture'),

    'google' => [
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'access_token' => env('GOOGLE_ADS_ACCESS_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
    ],

    'meta' => [
        'access_token' => env('META_ADS_ACCESS_TOKEN'),
    ],

    'linkedin' => [
        'access_token' => env('LINKEDIN_ADS_ACCESS_TOKEN'),
    ],

    // Days of daily metrics pulled per refresh.
    'metrics_window' => (int) env('ADVERTISING_METRICS_WINDOW', 30),
];
