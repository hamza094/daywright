# Scramble Upgrade Dependency Changes

## Upgrade Details

- **Previous version**: dedoc/scramble v0.13.22
- **New version**: dedoc/scramble v0.13.42
- **Target minimum**: v0.13.24 (for MiddlewareAuthSecurityStrategy)

## Key Dependency Changes

### Laravel Framework

- laravel/framework: v12.58.0 → v12.69.1

### Symfony Components

- symfony/http-foundation: v7.4.8 → v7.4.18
- symfony/http-kernel: v7.4.8 → v7.4.18
- symfony/console: v7.4.9 → v7.4.18
- symfony/mailer: v7.4.8 → v7.4.17
- symfony/event-dispatcher: v7.4.9 → v7.4.17
- symfony/error-handler: v7.4.8 → v7.4.17
- symfony/translation: v7.4.8 → v7.4.17
- symfony/mime: v7.4.9 → v7.4.18
- symfony/string: v7.4.8 → v7.4.15
- symfony/service-contracts: v3.6.1 → v3.7.3
- symfony/event-dispatcher-contracts: v3.6.0 → v3.7.1
- symfony/translation-contracts: v3.6.1 → v3.7.1

### Other Core Dependencies

- nesbot/carbon: 3.11.4 → 3.13.2
- monolog/monolog: 3.10.0 → 3.11.0
- guzzlehttp/guzzle: 7.10.0 → 7.15.5
- guzzlehttp/psr7: 2.9.0 → 2.13.1
- guzzlehttp/promises: 2.3.0 → 2.5.3
- guzzlehttp/uri-template: v1.0.5 → v1.0.11
- league/flysystem: 3.33.0 → 3.36.0
- league/flysystem-local: 3.31.0 → 3.35.3
- ramsey/uuid: 4.9.2 → 4.9.3

### Scramble Direct Dependencies

- phpstan/phpdoc-parser: 2.3.2 → 2.3.5
- nikic/php-parser: v5.7.0 → v5.8.0
- myclabs/deep-copy: 1.13.4 → 1.14.0
- spatie/laravel-package-tools: 1.93.0 → 1.93.2

### Development Dependencies

- mockery/mockery: 1.6.12 → 1.6.15
- hamcrest/hamcrest-php: v2.1.1 → v3.0.0

### PHP Polyfills

- Multiple symfony/polyfill-\* packages updated from v1.37.0 to v1.38.1/v1.41.0

## Assessment

All changes are minor version updates within stable release lines. No breaking changes expected. The Laravel framework update from v12.58.0 to v12.69.1 is within the same major version and should be backward compatible.

## Security Advisories

Composer reported 11 security vulnerability advisories affecting 6 packages. These should be reviewed separately with `composer audit`.
