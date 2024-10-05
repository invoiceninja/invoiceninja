<?php declare(strict_types=1);

return [
    'default' => env('ELASTIC_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'hosts' => [
                env('ELASTIC_HOST'),
            ],
            // configure basic authentication
            'basicAuthentication' => [
                env('ELASTIC_USERNAME'),
                env('ELASTIC_PASSWORD'),
            ],
            // configure HTTP client (Guzzle by default)
            'httpClientOptions' => [
                'timeout' => 2,
                'verify_host' => false,  // Disable SSL verification
                'verify_peer' => false,
                
            ],
        ],
    ],
];
