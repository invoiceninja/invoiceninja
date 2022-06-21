<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        // single database setup
        'mysql' => [
            'driver'         => 'mysql',
            'host'           => env('DB_HOST1', env('DB_HOST', '127.0.0.1')),
            'database'       => env('DB_DATABASE1', env('DB_DATABASE', 'forge')),
            'username'       => env('DB_USERNAME1', env('DB_USERNAME', 'forge')),
            'password'       => env('DB_PASSWORD1', env('DB_PASSWORD', '')),
            'port'           => env('DB_PORT1', env('DB_PORT', '3306')),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => env('DB_STRICT', false),
            'engine'         => 'InnoDB',
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
        ],

        'pgsql' => [
            'driver'         => 'pgsql',
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '5432'),
            'database'       => env('DB_DATABASE', 'forge'),
            'username'       => env('DB_USERNAME', 'forge'),
            'password'       => env('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'         => 'public',
            'sslmode'        => 'prefer',
        ],

        'sqlsrv' => [
            'driver'         => 'sqlsrv',
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '1433'),
            'database'       => env('DB_DATABASE', 'forge'),
            'username'       => env('DB_USERNAME', 'forge'),
            'password'       => env('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
        ],

        'db-ninja-01' => [
            'driver'         => 'mysql',
            'host'           => env('DB_HOST1', env('DB_HOST', '127.0.0.1')),
            'database'       => env('DB_DATABASE1', env('DB_DATABASE', 'forge')),
            'username'       => env('DB_USERNAME1', env('DB_USERNAME', 'forge')),
            'password'       => env('DB_PASSWORD1', env('DB_PASSWORD', '')),
            'port'           => env('DB_PORT1', env('DB_PORT', '3306')),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => env('DB_STRICT', false),
            'engine'         => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options'        => [],
            // 'options'        => [
            //     PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            //     PDO::MYSQL_ATTR_SSL_KEY => env("DB_CLIENT_KEY", ''),
            //     PDO::MYSQL_ATTR_SSL_CERT => env("DB_CLIENT_CERT", ''),
            //     PDO::MYSQL_ATTR_SSL_CA => env("DB_CA_CERT", ''),
            // ],
        ],

        'db-ninja-01a' => [
            'driver'         => 'mysql',
            'host'           => env('DB_HOST1', env('DB_HOST', '127.0.0.1')),
            'database'       => env('DB_DATABASE2', env('DB_DATABASE', 'forge')),
            'username'       => env('DB_USERNAME2', env('DB_USERNAME', 'forge')),
            'password'       => env('DB_PASSWORD2', env('DB_PASSWORD', '')),
            'port'           => env('DB_PORT1', env('DB_PORT', '3306')),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => env('DB_STRICT', false),
            'engine'         => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options'        => [],
        ],

        'db-ninja-02' => [
            'driver'         => 'mysql',
            'host'           => env('DB_HOST2', env('DB_HOST', '127.0.0.1')),
            'database'       => env('DB_DATABASE2', env('DB_DATABASE', 'forge')),
            'username'       => env('DB_USERNAME2', env('DB_USERNAME', 'forge')),
            'password'       => env('DB_PASSWORD2', env('DB_PASSWORD', '')),
            'port'           => env('DB_PORT2', env('DB_PORT', '3306')),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => env('DB_STRICT', false),
            'engine'         => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options'        => [],
        ],

        'db-ninja-02a' => [
            'driver'         => 'mysql',
            'host'           => env('DB_HOST2', env('DB_HOST', '127.0.0.1')),
            'database'       => env('DB_DATABASE1', env('DB_DATABASE', 'forge')),
            'username'       => env('DB_USERNAME1', env('DB_USERNAME', 'forge')),
            'password'       => env('DB_PASSWORD1', env('DB_PASSWORD', '')),
            'port'           => env('DB_PORT2', env('DB_PORT', '3306')),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => env('DB_STRICT', false),
            'engine'         => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options'        => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => 'predis',

        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

        'sentinel-default' => array_merge(
            array_map(
                function ($a, $b) {
                    return ['host' => $a, 'port' => $b];
                },
                explode(',', env('REDIS_HOST', 'localhost')),
                explode(',', env('REDIS_PORT', 26379))
            ),
            ['options' => [
                'replication' => 'sentinel',
                'service' =>  env('REDIS_SENTINEL_SERVICE', 'mymaster'),
                'sentinel_timeout' => 3.0,
                'parameters' => [
                    'password' => env('REDIS_PASSWORD', null),
                    'database' => env('REDIS_DB', 0),
                ],
            ]]
        ),

        'sentinel-cache' => array_merge(
            array_map(
                function ($a, $b) {
                    return ['host' => $a, 'port' => $b];
                },
                explode(',', env('REDIS_HOST', 'localhost')),
                explode(',', env('REDIS_PORT', 26379))
            ),
            ['options' => [
                'replication' => 'sentinel',
                'service' =>  env('REDIS_SENTINEL_SERVICE', 'mymaster'),
                'sentinel_timeout' => 3.0,
                'parameters' => [
                    'password' => env('REDIS_PASSWORD', null),
                    'database' => env('REDIS_CACHE_DB', 1),
                ],
            ]]
        ),

    ],

];
