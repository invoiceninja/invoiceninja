<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['X-API-COMPANY-KEY,X-API-SECRET,X-API-TOKEN,X-API-PASSWORD,DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Disposition,Content-Type,Range,X-CSRF-TOKEN,X-XSRF-TOKEN,X-LIVEWIRE'],

    'exposed_headers' => ['X-APP-VERSION,X-MINIMUM-CLIENT-VERSION,X-CSRF-TOKEN,X-XSRF-TOKEN,X-LIVEWIRE'],

    'max_age' => 0,

    'supports_credentials' => false,

];

// don't forget to check app/Http/Middleware/Cors.php
