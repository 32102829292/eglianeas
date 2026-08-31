<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geocoding endpoint configuration
    |--------------------------------------------------------------------------
    |
    | Geocoding goes through LocationIQ by default (its v1/search endpoint is
    | Nominatim-compatible and returns lat/lon/display_name in the same shape).
    | Nominatim rate-limited Render's outbound IPs, hence the provider switch.
    |
    | LOCATIONIQ_API_KEY is required for requests to succeed; callers send it
    | as the `key` query parameter. GEOCODING_URL can still override the
    | endpoint if you need to point at a different provider.
    */

    'url' => env('GEOCODING_URL', 'https://us1.locationiq.com/v1/search'),

    'api_key' => env('LOCATIONIQ_API_KEY'),

    'user_agent' => env('GEOCODING_USER_AGENT', 'EglianeAccountingServices/1.0 (contact: support@eglianeas.com; https://eglianeas.com)'),

    'timeout' => (int) env('GEOCODING_TIMEOUT', 8),

    'retries' => (int) env('GEOCODING_RETRIES', 1),

    'cache_ttl_hits' => (int) env('GEOCODING_CACHE_TTL_HITS', 86400),

    'cache_ttl_misses' => (int) env('GEOCODING_CACHE_TTL_MISSES', 1800),
];