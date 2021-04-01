<?php

return [

    'web_url' => 'https://www.invoiceninja.com',
    'license_url' => 'https://app.invoiceninja.com',
    'production' => env('NINJA_PROD', false),
    'license'   => env('NINJA_LICENSE', ''),
    'version_url' => 'https://raw.githubusercontent.com/invoiceninja/invoiceninja/v5-stable/VERSION.txt',
    'app_name' => env('APP_NAME', 'Invoice Ninja'),
    'app_env' => env('APP_ENV', 'selfhosted'),
    'debug_enabled' => env('APP_DEBUG', false),
    'require_https' => env('REQUIRE_HTTPS', true),
    'app_url' => rtrim(env('APP_URL', ''), '/'),
    'app_domain' => env('APP_DOMAIN', ''),
    'app_version' => '5.1.32',
    'minimum_client_version' => '5.0.16',
    'terms_version' => '1.0.1',
    'api_secret' => env('API_SECRET', false),
    'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    'google_analytics_url' => env('GOOGLE_ANALYTICS_URL', 'https://www.google-analytics.com/collect'),
    'key_length' => 64,
    'date_format' => 'Y-m-d',
    'date_time_format' => 'Y-m-d H:i',
    'daily_email_limit' => 300,
    'error_email' => env('ERROR_EMAIL', ''),
    'mailer' => env('MAIL_MAILER', ''),
    'company_id' => 0,
    'hash_salt' => env('HASH_SALT', ''),
    'currency_converter_api_key' => env('OPENEXCHANGE_APP_ID', ''),
    'enabled_modules' => 32767,
    'phantomjs_key' => env('PHANTOMJS_KEY', 'a-demo-key-with-low-quota-per-ip-address'),
    'phantomjs_secret' => env('PHANTOMJS_SECRET', false),
    'phantomjs_pdf_generation' => env('PHANTOMJS_PDF_GENERATION', true),
    'trusted_proxies' => env('TRUSTED_PROXIES', false),
    'is_docker' => env('IS_DOCKER', false),
    'sentry_dsn' => env('SENTRY_LARAVEL_DSN', 'https://9b4e15e575214354a7d666489783904a@sentry.invoicing.co/6'),
    'environment' => env('NINJA_ENVIRONMENT', 'selfhost'), // 'hosted', 'development', 'selfhost', 'reseller'
	'preconfigured_install' => env('PRECONFIGURED_INSTALL',false),

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
        'timezone_id' => env('DEFAULT_TIMEZONE', 1),
        'country_id' => env('DEFAULT_COUNTRY', 840), // United Stated
        'currency_id' => env('DEFAULT_CURRENCY', 1),
        'language_id' => env('DEFAULT_LANGUAGE', 1), //en
        'date_format_id' => env('DEFAULT_DATE_FORMAT_ID', '1'),
        'datetime_format_id' => env('DEFAULT_DATETIME_FORMAT_ID', '1'),
        'locale' => env('DEFAULT_LOCALE', 'en'),
        'map_zoom' => env('DEFAULT_MAP_ZOOM', 10),
        'payment_terms' => env('DEFAULT_PAYMENT_TERMS', ''),
        'military_time' => env('MILITARY_TIME', 0),
        'first_day_of_week' => env('FIRST_DATE_OF_WEEK', 0),
        'first_month_of_year' => env('FIRST_MONTH_OF_YEAR', '2000-01-01'),
    ],

    'testvars' => [
        'username' => 'user@example.com',
        'clientname' => 'client@example.com',
        'password' => 'password',
        'stripe' => env('STRIPE_KEYS', ''),
        'paypal' => env('PAYPAL_KEYS', ''),
        'authorize' => env('AUTHORIZE_KEYS', ''),
        'checkout' => env('CHECKOUT_KEYS', ''),
        'travis' => env('TRAVIS', false),
        'test_email' => env('TEST_EMAIL', 'test@example.com'),
    ],
    'contact' => [
        'email' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME'),
        'ninja_official_contact' => env('NINJA_OFFICIAL_CONTACT', 'contact@invoiceninja.com'),
    ],
    'cached_tables' => [
        'banks' => App\Models\Bank::class,
        'countries' => App\Models\Country::class,
        'currencies' => App\Models\Currency::class,
        'date_formats' => App\Models\DateFormat::class,
        'datetime_formats' => App\Models\DatetimeFormat::class,
        'gateways' => App\Models\Gateway::class,
        //'gateway_types' => App\Models\GatewayType::class,
        'industries' => App\Models\Industry::class,
        'languages' => App\Models\Language::class,
        'payment_types' => App\Models\PaymentType::class,
        'sizes' => App\Models\Size::class,
        'timezones' => App\Models\Timezone::class,
        //'invoiceDesigns' => 'App\Models\InvoiceDesign',
        //'invoiceStatus' => 'App\Models\InvoiceStatus',
        //'frequencies' => 'App\Models\Frequency',
        //'fonts' => 'App\Models\Font',
    ],
    'notification' => [
        'slack' => env('SLACK_WEBHOOK_URL', ''),
        'mail' => env('HOSTED_EMAIL', ''),
    ],
    'themes' => [
        'global' => 'ninja2020',
        'portal' => 'ninja2020',
    ],
    'quotas' => [
        'free' => [
            'clients' => 50,
            'daily_emails' => 50,
        ],
        'pro' => [
            'daily_emails' => 100,
        ],
        'enterprise' => [
            'daily_emails' => 200,
        ],
    ],
    'auth' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        ],
    ],
    'system' => [
        'node_path' => env('NODE_PATH', false),
        'npm_path' => env('NPM_PATH', false),
    ],
    'designs' => [
        'base_path' => resource_path('views/pdf-designs/'),
    ],
    'log_pdf_html' => env('LOG_PDF_HTML', false),
    'expanded_logging' => env('EXPANDED_LOGGING', false),
    'snappdf_chromium_path' => env('SNAPPDF_CHROMIUM_PATH', false),
    'v4_migration_version' => '4.5.35',
    'flutter_canvas_kit' => env('FLUTTER_CANVAS_KIT', 'selfhosted-html'),
    'webcron_secret' => env('WEBCRON_SECRET', false),
    'disable_auto_update' => env('DISABLE_AUTO_UPDATE', false),
    'invoiceninja_hosted_pdf_generation' => env('NINJA_HOSTED_PDF', false),
];
