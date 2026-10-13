<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Sms;

use RuntimeException;
use Throwable;

abstract class SmsException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    abstract public function isTransient(): bool;
}
