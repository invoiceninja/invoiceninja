<?php declare(strict_types=1);

return [
    'default' => env('ELASTIC_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'hosts' => [
                env('ELASTIC_HOST', 'https://localhost:9200'),
            ],
            // configure basic authentication
            'basicAuthentication' => [
                env('ELASTIC_USERNAME', 'elastic'),
                env('ELASTIC_PASSWORD', 'changeme'),
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
