<?php

/**
 * GLOBAL CONSTANTS ONLY
 *
 * Class constants to be assigned and accessed statically via
 * their model ie, Invoice::STATUS_DEFAULT
 *
 */

if (! defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'Invoice Ninja'));
    define('APP_DOMAIN', env('APP_DOMAIN', 'invoiceninja.com'));
    define('CONTACT_EMAIL', env('MAIL_FROM_ADDRESS'));
    define('CONTACT_NAME', env('MAIL_FROM_NAME'));
    define('SITE_URL', env('APP_URL'));
    define('APP_VERSION', env('APP_VERSION'));
    define('NINJA_TERMS_VERSION', '1.0.1');

    define('ENV_DEVELOPMENT', 'local');
    define('ENV_STAGING', 'staging');

    define('TEST_USERNAME', env('TEST_USERNAME', 'user@example.com'));
    define('TEST_CLIENTNAME', env('TEST_CLIENTNAME', 'client@example.com'));
    define('TEST_PASSWORD', 'password');

    define('BANK_LIBRARY_OFX', 1);
    define('MULTI_DBS', serialize(['db-ninja-1', 'db-ninja-2']));
    define('RANDOM_KEY_LENGTH', 32); //63340286662973277706162286946811886609896461828096 combinations

    define('SOCIAL_GOOGLE', 'Google');
    define('SOCIAL_FACEBOOK', 'Facebook');
    define('SOCIAL_GITHUB', 'GitHub');
    define('SOCIAL_LINKEDIN', 'LinkedIn');
    define('SOCIAL_TWITTER', 'Twitter');
    define('SOCIAL_BITBUCKET', 'Bitbucket');

    define('CURRENCY_DOLLAR', 1);
    define('CURRENCY_EURO', 3);

    define('DEFAULT_TIMEZONE', 'US/Eastern');
    define('DEFAULT_COUNTRY', 840); // United Stated
    define('DEFAULT_CURRENCY', CURRENCY_DOLLAR);
    define('DEFAULT_LANGUAGE', 1); // English
    define('DEFAULT_DATE_FORMAT', 'M j, Y');
    define('DEFAULT_DATE_PICKER_FORMAT', 'M d, yyyy');
    define('DEFAULT_DATETIME_FORMAT', 'F j, Y g:i a');
    define('DEFAULT_DATETIME_MOMENT_FORMAT', 'MMM D, YYYY h:mm:ss a');
    define('DEFAULT_LOCALE', 'en');
    define('DEFAULT_MAP_ZOOM', 10);
}