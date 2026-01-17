<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use App\Interfaces\Paddle;
use App\Models\User;

final class SubscriptionService implements Paddle
{
    /**
     * Create a new Paddle subscription for the given user on the specified plan.
     *
     * @param User $user The user to subscribe.
     * @param string $plan The plan identifier corresponding to services.paddle.{plan} configuration.
     * @return mixed The created subscription response returned by the Paddle client.
     * @throws SubscriptionException If the user is already subscribed to the specified plan.
     */
    public function subscribe(User $user, string $plan): mixed
    {
        if ($user->subscribedPlan() === $plan) {
            throw new SubscriptionException('You are already subscribed to this plan.');
        }

        return $user->newSubscription('DayWright', config('services.paddle.'.$plan))
            ->returnTo('http://localhost:8000/subscriptions')
            ->create();
    }

    /**
     * Swap the user's DayWright subscription to a different plan.
     *
     * Throws if the user is already on the requested plan. On success, updates the subscription and returns a confirmation message.
     *
     * @param User $user The user whose subscription will be changed.
     * @param string $plan The target plan key as defined in `services.paddle`.
     * @return array{message: string} Confirmation message about the updated plan.
     * @throws SubscriptionException If the user is already on the specified plan.
     */
    public function swap(User $user, string $plan): array
    {
        $currentPlan = $user->subscribedPlan();

        if ($currentPlan === $plan) {
            throw new SubscriptionException('You are already on this plan.');
        }

        $user->subscription('DayWright')->swapAndInvoice(config('services.paddle.'.$plan));

        return [
            'message' => 'Your subscription has been successfully updated to the '.$plan.' plan',
        ];
    }

    /**
     * Cancel the user's active 'DayWright' subscription when it matches the given plan.
     *
     * @param User $user The user whose subscription will be cancelled.
     * @param string $plan The plan identifier expected to match the user's current subscription.
     * @throws SubscriptionException If the user's current subscribed plan does not equal the provided plan.
     * @return array{message: string} An associative array with a confirmation message.
     */
    public function cancel(User $user, string $plan): array
    {
        if ($user->subscribedPlan() !== $plan) {
            throw new SubscriptionException('You are not subscribed to this plan.');
        }

        $user->subscription('DayWright')->cancel();

        return [
            'message' => 'Your subscription has been canceled successfully.',
        ];
    }
}