<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use Exception;

final class SubscriptionOperationValidationException extends Exception
{
    /** @var array<string, mixed> */
    private array $context = [];

    /** @param array<string, mixed> $context */
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 0, null);

        $this->context = $context;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }
}
