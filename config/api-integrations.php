<?php

return [
    'environment' => env('API_ENV', env('APP_ENV', 'production') === 'production' ? 'production' : 'quality'),
    'base_url' => env('API_ENV', env('APP_ENV', 'production') === 'production' ? 'production' : 'quality') === 'production'
        ? env('PRODUCTION_API_BASE_URL', 'https://fiori.apollopipes.com:44301')
        : env('QUALITY_API_BASE_URL', 'https://103.186.48.158:44321'),
    'username' => env('API_AUTH_USERNAME'),
    'password' => env('API_AUTH_PASSWORD'),
    'credentials' => [
        'API_AUTH_USERNAME' => env('API_AUTH_USERNAME'),
        'API_AUTH_PASSWORD' => env('API_AUTH_PASSWORD'),
        'QUALITY_API_TOKEN' => env('QUALITY_API_TOKEN'),
        'PRODUCTION_API_TOKEN' => env('PRODUCTION_API_TOKEN'),
        'PROCUREMENT_STOCK_API_TOKEN' => env('PROCUREMENT_STOCK_API_TOKEN'),
    ],
];
