<?php

return [

    'web_url' => 'https://www.invoiceninja.com',
    'app_name' => env('APP_NAME'),
    'site_url' => env('APP_URL', 'https://app-v5.invoiceninja.com'),
    'app_domain' => env('APP_DOMAIN', 'invoiceninja.com'),
    'app_version' => '0.1',
    'terms_version' => '1.0.1',
    'app_env' => env('APP_ENV', 'development'),
    'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),

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
    ],

    'i18n' => [
        'timezone' => env('DEFAULT_TIMEZONE', 'US/Eastern'),
        'country' => env('DEFAULT_COUNTRY', 840), // United Stated
        'currency' => env('DEFAULT_CURRENCY', 1), //USD
        'language' => env('DEFAULT_LANGUAGE', 1), //en
        'date_format' => env('DEFAULT_DATE_FORMAT', 'M j, Y'),
        'date_picker_format' => env('DEFAULT_DATE_PICKER_FORMAT', 'M d, yyyy'),
        'datetime_format' => env('DEFAULT_DATETIME_FORMAT', 'F j, Y g:i a'),
        'datetime_momemnt_format' => env('DEFAULT_DATETIME_MOMENT_FORMAT', 'MMM D, YYYY h:mm:ss a'),
        'locale' => env('DEFAULT_LOCALE', 'en'),
        'map_zoom' => env('DEFAULT_MAP_ZOOM', 10),
    ],

    'testvars' => [
        'username' => 'user@example.com',
        'clientname' => 'client@example.com',
        'password' => 'password',
    ],

    'contact' => [
        'email' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME'),
    ],

];
