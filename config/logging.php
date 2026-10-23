<?php

declare(strict_types=1);

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
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'nightwatch'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 3,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'daily_json' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 14,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
        'zoom' => [
            'driver' => 'daily',
            'path' => storage_path('logs/zoom.log'),
            'level' => env('LOG_ZOOM_LEVEL', 'info'),
            'days' => 30,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],
        'zoom_webhook_failed' => [
            'driver' => 'stack',
            'channels' => ['zoom_webhook_failed_file', 'nightwatch'],
            'ignore_exceptions' => false,
        ],
        'zoom_webhook_failed_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/zoom-webhook-failed.log'),
            'level' => 'error',
            'days' => 30,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'exception_metrics' => [
            'driver' => 'daily',
            'path' => storage_path('logs/exception-metrics.log'),
            'level' => 'info',
            'days' => 14,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],

        'queue_critical' => [
            'driver' => 'stack',
            'channels' => ['queue_critical_file', 'nightwatch'],
            'ignore_exceptions' => false,
        ],
        'queue_critical_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue-critical.log'),
            'level' => 'error',
            'days' => 30,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap' => [App\Logging\ScrubSensitiveData::class],
        ],
    ],

];
