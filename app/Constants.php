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

    define('ENV_DEVELOPMENT', 'local');
    define('ENV_STAGING', 'staging');

    define('TEST_USERNAME', env('TEST_USERNAME', 'user@example.com'));
    define('TEST_CLIENTNAME', env('TEST_CLIENTNAME', 'client@example.com'));
    define('TEST_PASSWORD', 'password');



    define('BANK_LIBRARY_OFX', 1);

}