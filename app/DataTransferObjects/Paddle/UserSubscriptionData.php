<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Paddle;

final readonly class UserSubscriptionData
{
    public function __construct(
        public int $vendorID,
        public string $vendorAuthCode,
        public int $resultsPerPage,
    ) {}
}
