<?php

declare(strict_types=1);

namespace App\Services\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use App\Interfaces\Paddle;
use App\Models\User;
use Override;

final class SubscriptionService implements Paddle
{
    #[Override]
    public function subscribe(User $user, string $plan): mixed
    {
        $this->validateSubscribeAllowed($user, $plan);

        $appUrl = rtrim((string) config('app.url'), '/');

        return $user->newSubscription('DayWright', config('services.paddle.'.$plan))
            ->returnTo($appUrl.'/subscriptions')
            ->create();
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function swap(User $user, string $plan): array
    {
        if (! $user->isBillingSubscribed()) {
            throw new SubscriptionException('You are not subscribed to a paid plan.');
        }

        $currentPlan = $user->activeBillingPlan();

        if ($currentPlan === $plan) {
            throw new SubscriptionException('You are already on this plan.');
        }

        $user->subscription('DayWright')->swapAndInvoice(config('services.paddle.'.$plan));

        return [
            'message' => 'Your subscription has been successfully updated to the '.$plan.' plan',
        ];
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function cancel(User $user, string $plan): array
    {
        if (! $user->isBillingSubscribed() || $user->activeBillingPlan() !== $plan) {
            throw new SubscriptionException('You are not subscribed to this plan.');
        }

        $user->subscription('DayWright')->cancel();

        return [
            'message' => 'Your subscription has been canceled successfully.',
        ];
    }

    private function validateSubscribeAllowed(User $user, string $plan): void
    {
        if (! $user->isSubscribed()) {
            return;
        }

        if ($user->isBillingSubscribed()) {
            throw new SubscriptionException(
                $user->activeBillingPlan() === $plan
                    ? 'You are already subscribed to this plan.'
                    : 'You already have an active paid plan. Please swap plans instead.'
            );
        }

        throw new SubscriptionException('You have an existing subscription. Please resume or swap your subscription instead.');
    }
}
