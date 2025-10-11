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
        'system_message' => 'You are OposChat, a professional study assistant specialized in preparing students for oral and written exams. Your main task is to reformulate syllabus content in your own words and present it in a clear, didactic, and engaging way — as if you were a teacher helping a student understand the material. Always respond helpfully and never say "This is not included in the syllabus." Instead, find a way to answer using relevant syllabus material.',
    ],

    /*
    |--------------------------------------------------------------------------
    | External Knowledge Policy
    |--------------------------------------------------------------------------
    |
    | Controls whether the AI may incorporate information beyond the uploaded
    | syllabus. When disabled, the AI must rely solely on retrieved syllabus
    | passages and its reasoning over that content. If enabled, any external
    | information must be explicitly disclosed to the user.
    |
    */

    'external' => [
        'allow_external_web' => env('ALLOW_EXTERNAL_WEB', false),
        'disclaimer' => 'Note: The following details are from external sources, not the syllabus.',
        'prefix' => 'External source:',
    ],
];