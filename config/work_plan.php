<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Work Plan Planning Window
    |--------------------------------------------------------------------------
    |
    | Planning opens on the configured weekday/time and stays open until the
    | configured lock weekday/time. The default window mirrors the office
    | policy: opens Friday 00:00 and locks Monday 12:00 Asia/Manila.
    |
    | Values may also be overridden per installation by creating rows in the
    | work_plan_settings table using the keys below.
    |
    */
    'timezone' => env('WORK_PLAN_TIMEZONE', 'Asia/Manila'),

    'opens_on' => env('WORK_PLAN_OPENS_ON', 'friday'),
    'opens_at' => env('WORK_PLAN_OPENS_AT', '00:00'),

    'locks_on' => env('WORK_PLAN_LOCKS_ON', 'monday'),
    'locks_at' => env('WORK_PLAN_LOCKS_AT', '12:00'),
];
