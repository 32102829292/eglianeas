<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geocoding endpoint configuration
    |--------------------------------------------------------------------------
    |
    | Geocoding goes through Nominatim (OpenStreetMap) by default. If Render's
    | outbound network is blocked or Nominatim refuses the request, override
    | the endpoint via GEOCODING_URL so the app can point at a compatible
    | provider (e.g. a Nominatim-compatible mirror) without code changes.
    |
    | Nominatim's usage policy requires a descriptive User-Agent identifying
    | the application and a real contact address or URL.
    */

    'url' => env('GEOCODING_URL', 'https://nominatim.openstreetmap.org/search'),

    'user_agent' => env('GEOCODING_USER_AGENT', 'EglianeAccountingServices/1.0 (contact: support@eglianeas.com; https://eglianeas.com)'),

    'timeout' => (int) env('GEOCODING_TIMEOUT', 8),

    'retries' => (int) env('GEOCODING_RETRIES', 1),

    'cache_ttl_hits' => (int) env('GEOCODING_CACHE_TTL_HITS', 86400),

    'cache_ttl_misses' => (int) env('GEOCODING_CACHE_TTL_MISSES', 1800),
];