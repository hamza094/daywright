<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Sms;

use Override;
use Throwable;

final class SmsProviderException extends SmsException
{
    /**
     * Transient Vonage status codes that may succeed on retry:
     * - 1: Throttled (rate limited by Vonage)
     * - 5: Internal Error (temporary platform glitch)
     * - 13: Communication Failed (temporary connection issue between Vonage and carrier)
     *
     * @var array<int, bool>
     */
    private const array TRANSIENT_STATUS_CODES = [
        1 => true,
        5 => true,
        13 => true,
    ];

    public function __construct(
        string $message,
        private readonly int $providerStatus = 0,
        private readonly bool $transient = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $providerStatus, $previous);
    }

    public static function fromStatus(int $status, ?string $detail = null): self
    {
        $isTransient = self::TRANSIENT_STATUS_CODES[$status] ?? false;
        $reason = self::statusReason($status);
        $message = "Vonage SMS failed with status [{$status}]: {$reason}".($detail !== null && $detail !== '' ? " ({$detail})" : '');

        return new self(
            message: $message,
            providerStatus: $status,
            transient: $isTransient,
        );
    }

    public static function transient(string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: $message,
            providerStatus: 0,
            transient: true,
            previous: $previous,
        );
    }

    public function providerStatus(): int
    {
        return $this->providerStatus;
    }

    #[Override]
    public function isTransient(): bool
    {
        return $this->transient;
    }

    private static function statusReason(int $status): string
    {
        return match ($status) {
            1 => 'Throttled',
            2 => 'Missing additional arguments',
            3 => 'Invalid credentials',
            4 => 'Invalid message',
            5 => 'Internal error',
            6 => 'Unroutable message',
            7 => 'Number barred',
            8 => 'Partner account barred',
            9 => 'Partner quota exceeded',
            11 => 'Account not enabled for HTTP',
            12 => 'Message too long',
            13 => 'Communication failed',
            14 => 'Invalid signature',
            15 => 'Invalid sender address',
            29 => 'Non-whitelisted destination',
            default => 'Unknown provider error',
        };
    }
}
