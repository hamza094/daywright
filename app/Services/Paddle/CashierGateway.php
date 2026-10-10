<?php

declare(strict_types=1);

namespace App\Services\Paddle;

use App\DataTransferObjects\Paddle\PaddleSubscriptionSnapshot;
use App\Interfaces\Paddle\CashierGatewayInterface;
use App\Models\User;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Subscription as PaddleSubscription;
use Override;
use Throwable;

final class CashierGateway implements CashierGatewayInterface
{
    #[Override]
    public function generatePayLink(User $user, int $planId, string $returnUrl): string
    {
        /** @var string $payLink */
        $payLink = $user->newSubscription($user->subscriptionName(), $planId)
            ->returnTo($returnUrl)
            ->create();

        return $payLink;
    }

    #[Override]
    public function swapAndInvoice(PaddleSubscription $subscription, int $planId): void
    {
        $subscription->swapAndInvoice($planId);
    }

    #[Override]
    public function cancel(PaddleSubscription $subscription): void
    {
        $subscription->cancel();
    }

    #[Override]
    public function getSubscription(int|string $paddleSubscriptionId): ?PaddleSubscriptionSnapshot
    {
        $vendorId = config('cashier.vendor_id');
        $vendorAuthCode = config('cashier.vendor_auth_code');

        if (empty($vendorId) || empty($vendorAuthCode)) {
            return null;
        }

        try {
            $response = Cashier::post('/subscription/users', [
                'vendor_id' => $vendorId,
                'vendor_auth_code' => $vendorAuthCode,
                'subscription_id' => (int) $paddleSubscriptionId,
            ]);

            $sub = $response['response'][0] ?? null;

            if (! is_array($sub)) {
                return null;
            }

            return PaddleSubscriptionSnapshot::fromPaddleResponse($sub);
        } catch (Throwable) {
            return null;
        }
    }
}
