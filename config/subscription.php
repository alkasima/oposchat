<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for subscription-based features and access control
    |
    */

    'features' => [
        'chat_messages' => [
            'name' => 'Chat Messages',
            'description' => 'Send messages in chat',
            'free_limit' => 50,
            'premium_limit' => null, // unlimited
        ],
        'file_uploads' => [
            'name' => 'File Uploads',
            'description' => 'Upload files to chat',
            'free_limit' => 10,
            'premium_limit' => null, // unlimited
        ],
        'api_calls' => [
            'name' => 'API Calls',
            'description' => 'Make API requests',
            'free_limit' => 100,
            'premium_limit' => null, // unlimited
        ],
    ],

    'premium_features' => [
        'advanced_analytics',
        'priority_support',
        'custom_themes',
        'export_data',
    ],

    'trial' => [
        'enabled' => true,
        'duration_days' => 14,
    ],

    'plans' => [
        'pro' => [
            'name' => 'Pro',
            'description' => 'Perfect for individuals and small teams',
            'price' => 9.99,
            'currency' => 'USD',
            'interval' => 'month',
            'stripe_price_id' => env('STRIPE_PRO_PRICE_ID', 'price_1RuE5gAVc1w1yLTUdkry1i2o'),
            'features' => [
                'Unlimited chat messages',
                'File uploads',
                'Priority support',
                'Advanced analytics',
            ],
        ],
        'team' => [
            'name' => 'Team',
            'description' => 'For growing teams and businesses',
            'price' => 19.99,
            'currency' => 'USD',
            'interval' => 'month',
            'stripe_price_id' => env('STRIPE_TEAM_PRICE_ID', 'price_1RuE5gAVc1w1yLTUopmMCnBb'),
            'features' => [
                'Everything in Pro',
                'Team collaboration',
                'Custom themes',
                'Export data',
                'Dedicated support',
            ],
        ],
    ],

    'free_plan' => [
        'name' => 'Free',
        'description' => 'Get started with basic features',
        'price' => 0,
        'features' => [
            '50 chat messages per month',
            '10 file uploads per month',
            'Basic support',
        ],
    ],

    'feature_comparison' => [
        'chat_messages' => [
            'free' => '50/month',
            'pro' => 'Unlimited',
            'team' => 'Unlimited',
        ],
        'file_uploads' => [
            'free' => '10/month',
            'pro' => 'Unlimited',
            'team' => 'Unlimited',
        ],
        'support' => [
            'free' => 'Community',
            'pro' => 'Priority',
            'team' => 'Dedicated',
        ],
        'analytics' => [
            'free' => false,
            'pro' => true,
            'team' => true,
        ],
    ],
];