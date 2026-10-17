<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DataTransferObjects\Subscription\SubscriptionOperationResult;
use App\Models\User;

interface Paddle
{
    public function subscribe(User $user, string $plan): string;

    public function swap(User $user, string $plan, string $idempotencyKey): SubscriptionOperationResult;

    public function cancel(User $user, string $plan, string $idempotencyKey): SubscriptionOperationResult;
}
