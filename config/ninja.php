<?php

return [

    'web_url' => 'https://www.invoiceninja.com',
    'app_name' => env('APP_NAME'),
    'site_url' => env('APP_URL', 'https://v2.invoiceninja.com'),
    'app_domain' => env('APP_DOMAIN', 'invoiceninja.com'),
    'app_version' => '0.1',
    'api_version' => '0.1',
    'terms_version' => '1.0.1',
    'app_env' => env('APP_ENV', 'development'),
    'api_secret' => env('API_SECRET', ''),
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
        'timezone_id' => env('DEFAULT_TIMEZONE', 15),
        'country_id' => env('DEFAULT_COUNTRY', 840), // United Stated
        'currency_id' => env('DEFAULT_CURRENCY', 1), //USD
        'language_id' => env('DEFAULT_LANGUAGE', 1), //en
        'date_format' => env('DEFAULT_DATE_FORMAT', 'M j, Y'),
        'date_picker_format' => env('DEFAULT_DATE_PICKER_FORMAT', 'M d, yyyy'),
        'datetime_format' => env('DEFAULT_DATETIME_FORMAT', 'F j, Y g:i a'),
        'datetime_moment_format' => env('DEFAULT_DATETIME_MOMENT_FORMAT', 'MMM D, YYYY h:mm:ss a'),
        'locale' => env('DEFAULT_LOCALE', 'en'),
        'map_zoom' => env('DEFAULT_MAP_ZOOM', 10),
        'payment_terms' => env('DEFAULT_PAYMENT_TERMS', 7),
        'military_time' => env('MILITARY_TIME', 0),
        'start_of_week' => env('START_OF_WEEK',1),
        'financial_year_start' => env('FINANCIAL_YEAR_START', '2000-01-01')
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
