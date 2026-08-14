<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Call tracking (CALL)
    |--------------------------------------------------------------------------
    | `fixture` is the tested default; `callrail` uses the live (untested) driver.
    */
    'calls_driver' => env('ANALYTICS_CALLS_DRIVER', 'fixture'),

    'callrail' => [
        'api_key' => env('CALLRAIL_API_KEY'),
        'account_id' => env('CALLRAIL_ACCOUNT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Benchmark data layer (BENCH)
    |--------------------------------------------------------------------------
    | Minimum number of contributing organizations before an anonymized peer
    | benchmark is emitted (k-anonymity floor). Aggregates computed from fewer
    | orgs are suppressed so no single tenant's data can be inferred.
    */
    'benchmark_min_cohort' => (int) env('ANALYTICS_BENCHMARK_MIN_COHORT', 3),
];
