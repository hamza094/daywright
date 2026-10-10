<?php

declare(strict_types=1);

return [
    'barryvdh/laravel-debugbar' => [
        'aliases' => [
            'Debugbar' => 'Barryvdh\\Debugbar\\Facades\\Debugbar',
        ],
        'providers' => [
            0 => 'Barryvdh\\Debugbar\\ServiceProvider',
        ],
    ],
    'cviebrock/eloquent-sluggable' => [
        'providers' => [
            0 => 'Cviebrock\\EloquentSluggable\\ServiceProvider',
        ],
    ],
    'dedoc/scramble' => [
        'providers' => [
            0 => 'Dedoc\\Scramble\\ScrambleServiceProvider',
        ],
    ],
    'intervention/image' => [
        'aliases' => [
            'Image' => 'Intervention\\Image\\Facades\\Image',
        ],
        'providers' => [
            0 => 'Intervention\\Image\\ImageServiceProvider',
        ],
    ],
    'kitloong/laravel-migrations-generator' => [
        'providers' => [
            0 => 'KitLoong\\MigrationsGenerator\\MigrationsGeneratorServiceProvider',
        ],
    ],
    'laragear/two-factor' => [
        'providers' => [
            0 => 'Laragear\\TwoFactor\\TwoFactorServiceProvider',
        ],
    ],
    'laravel/boost' => [
        'providers' => [
            0 => 'Laravel\\Boost\\BoostServiceProvider',
        ],
    ],
    'laravel/cashier-paddle' => [
        'providers' => [
            0 => 'Laravel\\Paddle\\CashierServiceProvider',
        ],
    ],
    'laravel/mcp' => [
        'aliases' => [
            'Mcp' => 'Laravel\\Mcp\\Server\\Facades\\Mcp',
        ],
        'providers' => [
            0 => 'Laravel\\Mcp\\Server\\McpServiceProvider',
        ],
    ],
    'laravel/pennant' => [
        'aliases' => [
            'Feature' => 'Laravel\\Pennant\\Feature',
        ],
        'providers' => [
            0 => 'Laravel\\Pennant\\PennantServiceProvider',
        ],
    ],
    'laravel/roster' => [
        'providers' => [
            0 => 'Laravel\\Roster\\RosterServiceProvider',
        ],
    ],
    'laravel/sanctum' => [
        'providers' => [
            0 => 'Laravel\\Sanctum\\SanctumServiceProvider',
        ],
    ],
    'laravel/sentinel' => [
        'providers' => [
            0 => 'Laravel\\Sentinel\\SentinelServiceProvider',
        ],
    ],
    'laravel/socialite' => [
        'aliases' => [
            'Socialite' => 'Laravel\\Socialite\\Facades\\Socialite',
        ],
        'providers' => [
            0 => 'Laravel\\Socialite\\SocialiteServiceProvider',
        ],
    ],
    'laravel/tinker' => [
        'providers' => [
            0 => 'Laravel\\Tinker\\TinkerServiceProvider',
        ],
    ],
    'maatwebsite/excel' => [
        'aliases' => [
            'Excel' => 'Maatwebsite\\Excel\\Facades\\Excel',
        ],
        'providers' => [
            0 => 'Maatwebsite\\Excel\\ExcelServiceProvider',
        ],
    ],
    'nesbot/carbon' => [
        'providers' => [
            0 => 'Carbon\\Laravel\\ServiceProvider',
        ],
    ],
    'nunomaduro/collision' => [
        'providers' => [
            0 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
        ],
    ],
    'nunomaduro/termwind' => [
        'providers' => [
            0 => 'Termwind\\Laravel\\TermwindServiceProvider',
        ],
    ],
    'opcodesio/log-viewer' => [
        'aliases' => [
            'LogViewer' => 'Opcodes\\LogViewer\\Facades\\LogViewer',
        ],
        'providers' => [
            0 => 'Opcodes\\LogViewer\\LogViewerServiceProvider',
        ],
    ],
    'saloonphp/laravel-plugin' => [
        'aliases' => [
            'Saloon' => 'Saloon\\Laravel\\Facades\\Saloon',
        ],
        'providers' => [
            0 => 'Saloon\\Laravel\\SaloonServiceProvider',
        ],
    ],
    'spatie/laravel-backup' => [
        'providers' => [
            0 => 'Spatie\\Backup\\BackupServiceProvider',
        ],
    ],
    'spatie/laravel-ignition' => [
        'aliases' => [
            'Flare' => 'Spatie\\LaravelIgnition\\Facades\\Flare',
        ],
        'providers' => [
            0 => 'Spatie\\LaravelIgnition\\IgnitionServiceProvider',
        ],
    ],
    'spatie/laravel-query-builder' => [
        'providers' => [
            0 => 'Spatie\\QueryBuilder\\QueryBuilderServiceProvider',
        ],
    ],
    'spatie/laravel-signal-aware-command' => [
        'aliases' => [
            'Signal' => 'Spatie\\SignalAwareCommand\\Facades\\Signal',
        ],
        'providers' => [
            0 => 'Spatie\\SignalAwareCommand\\SignalAwareCommandServiceProvider',
        ],
    ],
    'torann/geoip' => [
        'aliases' => [
            'GeoIP' => 'Torann\\GeoIP\\Facades\\GeoIP',
        ],
        'providers' => [
            0 => 'Torann\\GeoIP\\GeoIPServiceProvider',
        ],
    ],
    'wendelladriel/laravel-idempotency' => [
        'providers' => [
            0 => 'WendellAdriel\\Idempotency\\Providers\\IdempotencyServiceProvider',
        ],
    ],
];
