<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supabase URL
    |--------------------------------------------------------------------------
    |
    | Your Supabase project URL. Used for API calls, Auth, Storage, and
    | Realtime features when you wire them up later.
    |
    */
    'url' => env('SUPABASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Supabase Publishable (anon) Key
    |--------------------------------------------------------------------------
    |
    | The public / anon key. Safe to expose in client-side code. Used for
    | Row Level Security policies and Supabase REST API calls.
    |
    */
    'publishable_key' => env('SUPABASE_PUBLISHABLE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Supabase Secret (service_role) Key
    |--------------------------------------------------------------------------
    |
    | The secret / service_role key. Never expose this in client-side code.
    | Bypasses Row Level Security — server-side use only.
    |
    */
    'secret_key' => env('SUPABASE_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Supabase JWKS URL
    |--------------------------------------------------------------------------
    |
    | JSON Web Key Set endpoint for verifying Supabase Auth JWTs.
    |
    */
    'jwks_url' => env('SUPABASE_JWKS_URL'),

];
