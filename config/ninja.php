<?php

return [

    'web_url' => 'https://www.invoiceninja.com',
    'app_url' => 'https://app-v5.invoiceninja.com',
    'site_url' => '',
    'environment' => env('NINJA_ENVIRONMENT', 'selfhost'), // 'hosted', 'development', 'selfhost', 'reseller'

    // Settings used by invoiceninja.com

    'terms_of_service_url' => [
        'hosted' => env('TERMS_OF_SERVICE_URL', 'https://www.invoiceninja.com/terms/'),
        'selfhost' => env('TERMS_OF_SERVICE_URL', 'https://www.invoiceninja.com/self-hosting-terms-service/'),
    ],

    'privacy_policy_url' => [
        'hosted' => env('PRIVACY_POLICY_URL', 'https://www.invoiceninja.com/privacy-policy/'),
        'selfhost' => env('PRIVACY_POLICY_URL', 'https://www.invoiceninja.com/self-hosting-privacy-data-control/'),
    ],

    'db' => [
        'multi_db_enabled' => env('MULTI_DB_ENABLED', false),
        'default' => env('DB_CONNECTION', 'mysql'),
    ]
];
