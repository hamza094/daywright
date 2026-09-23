<?php

declare(strict_types=1);

namespace App\Interfaces\Paddle;

use App\DataTransferObjects\Paddle\PaddleSubscriptionSnapshot;
use App\Models\User;
use Laravel\Paddle\Subscription as PaddleSubscription;

interface CashierGatewayInterface
{
    public function generatePayLink(User $user, int $planId, string $returnUrl): string;

    public function swapAndInvoice(PaddleSubscription $subscription, int $planId): void;

    public function cancel(PaddleSubscription $subscription): void;

    /**
     * Read-only lookup of subscription details from Paddle Classic API.
     */
    public function getSubscription(int|string $paddleSubscriptionId): ?PaddleSubscriptionSnapshot;
}
