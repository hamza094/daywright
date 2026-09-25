<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use Exception;

final class SubscriptionOperationValidationException extends Exception
{
    /** @param array<string, mixed> $context */
    public function __construct(string $message, private readonly array $context = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }
}
