<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by the
    | application. You may set this to any of the providers defined below.
    |
    | Supported: "openai", "gemini"
    |
    */

    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI providers for your application. Each
    | provider has its own configuration options.
    |
    */

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => 'https://api.openai.com/v1',
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Chat Settings
    |--------------------------------------------------------------------------
    |
    | These are the default settings for chat completions.
    |
    */

    'defaults' => [
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'system_message' => 'You are a helpful AI assistant specialized in exam preparation. Be concise, accurate, and friendly in your responses. Focus on providing exam-specific guidance and study strategies.',
    ],
];