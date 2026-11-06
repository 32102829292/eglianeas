<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most Laravel applications are configured with a single view directory
    | which contains all of the HTML, Blade, and PHP views. However, you are
    | free to specify multiple directories which Laravel will search through
    | whenever it is attempting to locate a view.
    |
    */

    'paths' => [resource_path('views')],

    /*
    |--------------------------------------------------------------------------
    | View Compilation
    |--------------------------------------------------------------------------
    |
    | Blade views are compiled into raw PHP to improve rendering performance.
    | You may configure the directory where these compiled views are stored.
    |
    */

    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

];