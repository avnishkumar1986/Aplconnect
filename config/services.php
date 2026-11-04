<?php

return [
    'api_integrations' => [
        'environment_code' => env('API_ENVIRONMENT', 'production'),
        'allowed_hosts' => env('API_ALLOWED_HOSTS', ''),
        'insecure_tls_hosts' => env('API_INSECURE_TLS_HOSTS', ''),
        'connect_timeout_seconds' => (int) env('API_CONNECT_TIMEOUT_SECONDS', 10),
        'credentials' => [
            'PROCUREMENT_STOCK_API_TOKEN' => [
                'username' => env('API_AUTH_USERNAME'),
                'password' => env('API_AUTH_PASSWORD'),
                'token' => env('PROCUREMENT_STOCK_API_TOKEN'),
            ],
            'QUALITY_API_TOKEN' => ['token' => env('QUALITY_API_TOKEN')],
            'PRODUCTION_API_TOKEN' => ['token' => env('PRODUCTION_API_TOKEN')],
        ],
    ],

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

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', env('APP_URL').'/auth/microsoft/callback'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'apollopipes.com'),
        'allowed_domain' => env('MICROSOFT_ALLOWED_DOMAIN', 'apollopipes.com'),
    ],

    'sms_otp' => [
        'webhook_url' => env('SMS_OTP_WEBHOOK_URL'),
        'token' => env('SMS_OTP_WEBHOOK_TOKEN'),
        'sender' => env('SMS_OTP_SENDER'),
    ],

];
