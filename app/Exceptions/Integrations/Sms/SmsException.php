<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Sms;

use RuntimeException;

abstract class SmsException extends RuntimeException
{
    abstract public function isTransient(): bool;
}
