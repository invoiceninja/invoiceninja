<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Postmark credentials
    |--------------------------------------------------------------------------
    |
    | Here you may provide your Postmark server API token.
    |
    */

    'secret' => env('POSTMARK_SECRET'),

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
        'timeout' => 10,
        'connect_timeout' => 10,
    ],
];
