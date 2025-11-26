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
            'free_limit' => 3, // 3 messages per day
            'premium_limit' => 200, // 200 messages per month
            'plus_limit' => null, // unlimited
            'academy_limit' => null, // unlimited
        ],
        'file_uploads' => [
            'name' => 'File Uploads',
            'description' => 'Upload files to chat',
            'free_limit' => 0, // No file uploads for free users
            'premium_limit' => null, // unlimited
            'plus_limit' => null, // unlimited
            'academy_limit' => null, // unlimited
        ],
        'api_calls' => [
            'name' => 'API Calls',
            'description' => 'Make API requests',
            'free_limit' => 100,
            'premium_limit' => null, // unlimited
            'plus_limit' => null, // unlimited
            'academy_limit' => null, // unlimited
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
        'premium' => [
            'name' => 'Premium',
            'description' => 'Perfect for individuals and small teams',
            'price' => 9.99,
            'currency' => 'EUR',
            'interval' => 'month',
            'stripe_price_id' => env('STRIPE_PREMIUM_PRICE_ID', 'price_1RuE5gAVc1w1yLTUdkry1i2o'),
            'features' => [
                '200 messages per month',
                'Upload files',
                'Access to exams',
                'Priority technical support',
            ],
        ],
        'plus' => [
            'name' => 'Plus',
            'description' => 'For growing teams and businesses',
            'price' => 14.99,
            'currency' => 'EUR',
            'interval' => 'month',
            'stripe_price_id' => env('STRIPE_PLUS_PRICE_ID', 'price_1RuE5gAVc1w1yLTUopmMCnBb'),
            'features' => [
                'Unlimited messages',
                'Upload files',
                'Access to exams',
                'Priority technical support',
            ],
        ],
        'academy' => [
            'name' => 'Academy',
            'description' => 'For institutions and large organizations',
            'price' => null, // Variable pricing - contact for quote
            'currency' => 'EUR',
            'interval' => 'month',
            // Use a configurable Stripe price ID if available, otherwise fall back to a manual marker ID
            // so that admin-granted Academy plans can still work without Stripe.
            'stripe_price_id' => env('STRIPE_ACADEMY_PRICE_ID', 'academy_manual'),
            'contact_sales' => true, // Flag to show contact sales instead of price
            'features' => [
                'Unlimited messages',
                'Upload files',
                'Access to exams',
                'Priority technical support',
                'Advanced analytics',
            ],
        ],
    ],

    'free_plan' => [
        'name' => 'Free',
        'description' => 'Get started with basic features',
        'price' => 0,
        'features' => [
            '3 messages per day',
            'Community support',
        ],
    ],

        'feature_comparison' => [
            'chat_messages' => [
                'free' => '3/day',
                'premium' => '200/month',
                'plus' => 'Unlimited',
                'academy' => 'Unlimited',
            ],
            'file_uploads' => [
                'free' => 'Not available',
                'premium' => 'Unlimited',
                'plus' => 'Unlimited',
                'academy' => 'Unlimited',
            ],
        'exams' => [
            'free' => false,
            'premium' => true,
            'plus' => true,
            'academy' => true,
        ],
        'support' => [
            'free' => 'Community',
            'premium' => 'Priority technical',
            'plus' => 'Priority technical',
            'academy' => 'Priority technical',
        ],
    ],
];