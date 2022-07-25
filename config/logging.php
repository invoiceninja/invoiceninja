<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [

        'invoiceninja' => [
            'driver' => 'single',
            'path' => storage_path('logs/invoiceninja.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

    'gelf' => [
        'driver' => 'custom',

        'via' => \Hedii\LaravelGelfLogger\GelfLoggerFactory::class,

        // This optional option determines the processors that should be
        // pushed to the handler. This option is useful to modify a field
        // in the log context (see NullStringProcessor), or to add extra
        // data. Each processor must be a callable or an object with an
        // __invoke method: see monolog documentation about processors.
        // Default is an empty array.
        'processors' => [
            \Hedii\LaravelGelfLogger\Processors\NullStringProcessor::class,
            // another processor...
        ],

        // This optional option determines the minimum "level" a message
        // must be in order to be logged by the channel. Default is 'debug'
        'level' => 'debug',

        // This optional option determines the channel name sent with the
        // message in the 'facility' field. Default is equal to app.env
        // configuration value
        'name' => 'v5_app',

        // This optional option determines the system name sent with the
        // message in the 'source' field. When forgotten or set to null,
        // the current hostname is used.
        'system_name' => null,

        // This optional option determines if you want the UDP, TCP or HTTP
        // transport for the gelf log messages. Default is UDP
        'transport' => 'udp',

        // This optional option determines the host that will receive the
        // gelf log messages. Default is 127.0.0.1
        'host' => env('GRAYLOG_SERVER', '127.0.0.1'),

        // This optional option determines the port on which the gelf
        // receiver host is listening. Default is 12201
        'port' => 12201,

        // This optional option determines the path used for the HTTP
        // transport. When forgotten or set to null, default path '/gelf'
        // is used.
        'path' => null,

        // This optional option determines the maximum length per message
        // field. When forgotten or set to null, the default value of
        // \Monolog\Formatter\GelfMessageFormatter::DEFAULT_MAX_LENGTH is
        // used (currently this value is 32766)
        'max_length' => null,

        // This optional option determines the prefix for 'context' fields
        // from the Monolog record. Default is null (no context prefix)
        'context_prefix' => null,

        // This optional option determines the prefix for 'extra' fields
        // from the Monolog record. Default is null (no extra prefix)
        'extra_prefix' => null,
    ],


];
