<?php

declare(strict_types=1);

namespace App\Services\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use App\Interfaces\Paddle;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Override;

final class SubscriptionService implements Paddle
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    #[Override]
    public function subscribe(User $user, string $plan): mixed
    {
        return $this->executeSerially($user, function (User $lockedUser) use ($plan): mixed {
            $this->validateSubscribeAllowed($lockedUser, $plan);

            $appUrl = rtrim((string) config('app.url'), '/');

            return $lockedUser->newSubscription('DayWright', config('services.paddle.'.$plan))
                ->returnTo($appUrl.'/subscriptions')
                ->create();
        });
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function swap(User $user, string $plan): array
    {
        return $this->executeSerially($user, function (User $lockedUser) use ($plan): array {
            if (! $lockedUser->isBillingSubscribed()) {
                throw new SubscriptionException('You are not subscribed to a paid plan.');
            }

            $currentPlan = $lockedUser->activeBillingPlan();

            if ($currentPlan === $plan) {
                throw new SubscriptionException('You are already on this plan.');
            }

            $lockedUser->subscription('DayWright')->swapAndInvoice(config('services.paddle.'.$plan));

            return [
                'message' => 'Your subscription has been successfully updated to the '.$plan.' plan',
            ];
        });
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function cancel(User $user, string $plan): array
    {
        return $this->executeSerially($user, function (User $lockedUser) use ($plan): array {
            if (! $lockedUser->isBillingSubscribed()) {
                return [
                    'message' => 'Your subscription has been canceled successfully.',
                ];
            }

            if ($lockedUser->activeBillingPlan() !== $plan) {
                throw new SubscriptionException('You are not subscribed to this plan.');
            }

            $lockedUser->subscription('DayWright')->cancel();

            return [
                'message' => 'Your subscription has been canceled successfully.',
            ];
        });
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

    /**
     * @template TReturn
     *
     * @param  Closure(User): TReturn  $callback
     * @return TReturn
     */
    private function executeSerially(User $user, Closure $callback): mixed
    {
        if (! $user->exists) {
            return $callback($user);
        }

        return DB::transaction(function () use ($user, $callback): mixed {
            $lockedUser = $this->lockUser($user);

            return $callback($lockedUser);
        }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);
    }

    private function lockUser(User $user): User
    {
        /** @var User $lockedUser */
        $lockedUser = User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedUser->loadMissing('subscriptions', 'customer');
    }
}
