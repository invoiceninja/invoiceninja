<?php

if (!defined('APP_NAME'))
{
    define('APP_NAME', env('APP_NAME', 'Invoice Ninja'));
    define('CONTACT_EMAIL', env('MAIL_FROM_ADDRESS'));
    define('CONTACT_NAME', env('MAIL_FROM_NAME'));
    define('SITE_URL', env('APP_URL'));
}
