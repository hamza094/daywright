<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

class ScrubSensitiveData
{
    public const string REDACTED = '********';

    /** @var array<string> */
    private const array SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'client_secret',
        'api_key',
        'x_api_key',
        'secret',
        'secret_key',
        'webhook_secret',
        'vendor_auth_code',
        'code_verifier',
        'recovery_code',
        'recovery_codes',
        'cookie',
        'set_cookie',
        'cc_number',
        'card_number',
        'cvv',
    ];

    public function __invoke(object $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord|array $record) {
                // Handle both Monolog 2 (array) and Monolog 3 (LogRecord)
                $data = $record instanceof LogRecord ? $record->toArray() : $record;

                $data['context'] = $this->sanitize($data['context']);
                $data['extra'] = $this->sanitize($data['extra']);

                return $record instanceof LogRecord
                    ? $record->with(context: $data['context'], extra: $data['extra'])
                    : $data;
            });
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        foreach ($data as $key => &$value) {
            if ($this->isSensitiveKey($key)) {
                $value = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string|int $key): bool
    {
        $normalized = $this->normalizeKey($key);

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_contains($normalized, 'password')
            || str_ends_with($normalized, '_token')
            || str_ends_with($normalized, '_secret')
            || str_ends_with($normalized, '_secret_key')
            || str_ends_with($normalized, '_auth_code');
    }

    private function normalizeKey(string|int $key): string
    {
        return str_replace(
            ['-', ' '],
            '_',
            mb_strtolower((string) $key),
        );
    }
}
