<?php

declare(strict_types=1);

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

$jsonFormatter = Monolog\Formatter\JsonFormatter::class;
$scrubTap = [App\Logging\ScrubSensitiveData::class];

// Shared shape for rotating JSON file channels; override path/level/days per channel.
$dailyJsonDefaults = [
    'driver' => 'daily',
    'level' => 'debug',
    'formatter' => $jsonFormatter,
    'tap' => $scrubTap,
];

// Stack channels that mirror to Nightwatch alongside a file channel.
$nightwatchStack = fn (string $fileChannel): array => [
    'driver' => 'stack',
    'channels' => [$fileChannel, 'nightwatch'],
    'ignore_exceptions' => false,
];

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
        'stack' => $nightwatchStack('daily'),

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'formatter' => $jsonFormatter,
            'tap' => $scrubTap,
        ],

        'daily' => array_merge($dailyJsonDefaults, [
            'path' => storage_path('logs/laravel.log'),
            'days' => 3,
        ]),

        'daily_json' => array_merge($dailyJsonDefaults, [
            'path' => storage_path('logs/laravel.log'),
            'days' => 14,
        ]),

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
            'tap' => $scrubTap,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
            'tap' => $scrubTap,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'tap' => $scrubTap,
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
            'tap' => $scrubTap,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
            'tap' => $scrubTap,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
        'zoom' => array_merge($dailyJsonDefaults, [
            'path' => storage_path('logs/zoom.log'),
            'level' => env('LOG_ZOOM_LEVEL', 'info'),
            'days' => 30,
        ]),
        'zoom_webhook_failed' => $nightwatchStack('zoom_webhook_failed_file'),
        'zoom_webhook_failed_file' => array_merge($dailyJsonDefaults, [
            'path' => storage_path('logs/zoom-webhook-failed.log'),
            'level' => 'error',
            'days' => 30,
        ]),

        'exception_metrics' => array_merge($dailyJsonDefaults, [
            'path' => storage_path('logs/exception-metrics.log'),
            'level' => 'info',
            'days' => 14,
        ]),

        'queue_critical' => $nightwatchStack('queue_critical_file'),
        'queue_critical_file' => array_merge($dailyJsonDefaults, [
            'path' => storage_path('logs/queue-critical.log'),
            'level' => 'error',
            'days' => 30,
        ]),
    ],

];
