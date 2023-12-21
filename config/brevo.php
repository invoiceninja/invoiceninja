<?php

return [

    /* @deprecated
    |--------------------------------------------------------------------------
    | Brevo credentials
    |--------------------------------------------------------------------------
    |
    | Here you may provide your Postmark server API token.
    |
    */

    'secret' => env('BREVO_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Guzzle options
    |--------------------------------------------------------------------------
    |
    | Under the hood we use Guzzle to make API calls to Postmark.
    | Here you may provide any request options for Guzzle.
    |
    */

    'guzzle' => [
        'timeout' => 120,
        'connect_timeout' => 120,
    ],
];
