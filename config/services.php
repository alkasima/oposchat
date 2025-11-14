<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'from_address' => env('AWS_SES_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'soporte@oposchat.com')),
    ],

    'email_api' => [
        'url' => env('EMAIL_API_URL', 'https://oposchat.ipzmarketing.com/api/v1/send_emails'),
        'token' => env('EMAIL_API_TOKEN'),
        'from_email' => env('EMAIL_API_FROM_EMAIL', 'soporte@oposchat.com'),
        'from_name' => env('EMAIL_API_FROM_NAME', 'Oposchat'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT'),
        'index_name' => env('PINECONE_INDEX_NAME', 'oposchat'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

];
