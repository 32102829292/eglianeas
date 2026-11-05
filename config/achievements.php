<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Achievement categories
    |--------------------------------------------------------------------------
    | Reviewers can only correct an assessment to one of these categories.
    */
    'categories' => [
        'Productivity',
        'Quality and Accuracy',
        'Initiative',
        'Problem Solving',
        'Teamwork',
        'Client Service',
        'Timeliness',
        'Process Improvement',
        'Learning and Development',
    ],

    /*
    |--------------------------------------------------------------------------
    | Achievement levels
    |--------------------------------------------------------------------------
    | Levels are derived from the score. Change a threshold here and the whole
    | module (analysis, review form, and rankings) follows automatically.
    */
    'levels' => [
        ['min' => 90, 'label' => 'Outstanding'],
        ['min' => 80, 'label' => 'Excellent'],
        ['min' => 70, 'label' => 'Very Good'],
        ['min' => 60, 'label' => 'Good'],
        ['min' => 0, 'label' => 'Needs Further Development'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recognition tags
    |--------------------------------------------------------------------------
    */
    'tags' => [
        'Results-oriented',
        'Initiative',
        'Problem Solving',
        'Teamwork',
        'Client Service',
        'Timeliness',
        'Process Improvement',
        'Learning',
        'Quality Focus',
        'Evidence-backed',
        'Consistency',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional AI provider
    |--------------------------------------------------------------------------
    | Calls are server-side only. Leave AI disabled (the default) to use the
    | transparent rule-based analyzer. Secrets are never sent to the browser.
    */
    'ai' => [
        'enabled' => (bool) env('ACHIEVEMENT_AI_ENABLED', false),
        'url' => env('ACHIEVEMENT_AI_URL', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('ACHIEVEMENT_AI_API_KEY'),
        'model' => env('ACHIEVEMENT_AI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('ACHIEVEMENT_AI_TIMEOUT', 15),
    ],
];
