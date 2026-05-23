<?php

return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model'   => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'candidate_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('GEMINI_CANDIDATE_MODELS', 'gemini-2.5-flash,gemini-2.5-pro'))
        ))),
    ],
];
