<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Sms;

use Override;
use Throwable;

final class SmsMissingRecipientException extends SmsException
{
    public function __construct(
        string $message = 'No mobile recipient available for SMS delivery.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    #[Override]
    public function isTransient(): bool
    {
        return false;
    }
}
