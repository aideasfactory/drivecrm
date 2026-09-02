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

    'mandrill' => [
        'key' => env('MANDRILL_API_KEY'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
    | Transactional email via Resend. MAIL_MAILER=resend reads this key.
    | Leave empty in local .env until the production/staging key is supplied.
    */
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
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
        'mobile_return_deeplink' => env('STRIPE_MOBILE_RETURN_DEEPLINK', 'drive-app://stripe-onboarding'),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'instructor' => [
        'search_radius_miles' => env('INSTRUCTOR_SEARCH_RADIUS_MILES', 10),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'amazon/nova-lite-v1'),
        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 1024),
    ],

    'mobile_app' => [
        'ios_url' => env('MOBILE_APP_IOS_URL'),
        'android_url' => env('MOBILE_APP_ANDROID_URL'),
    ],

    'bird' => [
        'enabled' => filter_var(env('BIRD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'api_key' => env('BIRD_API_KEY'),
        'workspace_id' => env('BIRD_WORKSPACE_ID'),
        'booking_list_id' => env('BIRD_BOOKING_LIST_ID'),
        'ai_channel_id' => env('BIRD_AI_CHANNEL_ID'),
        // Separate read-only key for the AI conversations screen so the
        // contact-sync key's roles are never touched. Falls back to the main
        // key when unset.
        'conversations_api_key' => env('BIRD_CONVERSATIONS_API_KEY', env('BIRD_API_KEY')),
    ],

    'gtm' => [
        'container_id' => env('GTM_CONTAINER_ID', 'GTM-56KMNJR'),
    ],

    'google_tag' => [
        'ads_id' => env('GOOGLE_ADS_ID', 'AW-10884289539'),
        'ga4_id' => env('GOOGLE_GA4_ID', 'G-NBYWT0EZF6'),
    ],

];
