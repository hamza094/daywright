<?php

declare(strict_types=1);

namespace App\Services\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use App\Interfaces\Paddle;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Override;
use Throwable;

final class SubscriptionService implements Paddle
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    #[Override]
    public function subscribe(User $user, string $plan): mixed
    {
        return $this->executeSerially($user, function (User $lockedUser) use ($plan): mixed {
            $this->validateSubscribeAllowed($lockedUser, $plan);
            $this->validatePlanConfig($plan, 'subscribe');

            $appUrl = rtrim((string) config('app.url'), '/');

            try {
                return $lockedUser->newSubscription($lockedUser->subscriptionName(), config('services.paddle.'.$plan))
                    ->returnTo($appUrl.'/subscriptions')
                    ->create();
            } catch (Throwable $e) {
                Log::error('Paddle API Exception during subscribe', [
                    'user_id' => $lockedUser->id,
                    'plan' => $plan,
                    'exception' => $e,
                ]);
                throw $e;
            }
        });
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function swap(User $user, string $plan): array
    {
        return $this->executeSerially($user, function (User $lockedUser) use ($plan): array {
            $this->validatePlanConfig($plan, 'swap');

            if (! $lockedUser->isBillingSubscribed()) {
                throw new SubscriptionException(
                    'You are not subscribed to a paid plan.',
                    action: 'swap',
                    plan: $plan,
                    currentState: $lockedUser->subscription($lockedUser->subscriptionName())?->paddle_status
                );
            }

            $currentPlan = $lockedUser->activeBillingPlan();

            if ($currentPlan === $plan) {
                throw new SubscriptionException(
                    'You are already on this plan.',
                    action: 'swap',
                    plan: $plan,
                    currentState: $lockedUser->subscription($lockedUser->subscriptionName())?->paddle_status
                );
            }

            try {
                $lockedUser->subscription($lockedUser->subscriptionName())->swapAndInvoice(config('services.paddle.'.$plan));
            } catch (Throwable $e) {
                Log::error('Paddle API Exception during swap', [
                    'user_id' => $lockedUser->id,
                    'plan' => $plan,
                    'exception' => $e,
                ]);
                throw $e;
            }

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
                throw new SubscriptionException(
                    'You are not subscribed to this plan.',
                    action: 'cancel',
                    plan: $plan,
                    currentState: $lockedUser->subscription($lockedUser->subscriptionName())?->paddle_status
                );
            }

            try {
                $lockedUser->subscription($lockedUser->subscriptionName())->cancel();
            } catch (Throwable $e) {
                Log::error('Paddle API Exception during cancel', [
                    'user_id' => $lockedUser->id,
                    'plan' => $plan,
                    'exception' => $e,
                ]);
                throw $e;
            }

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
            $currentPlan = $user->activeBillingPlan();

            throw new SubscriptionException(
                $currentPlan === $plan
                    ? 'You are already subscribed to this plan.'
                    : 'You already have an active paid plan. Please swap plans instead.',
                action: 'subscribe',
                plan: $plan,
                currentState: $user->subscription($user->subscriptionName())?->paddle_status
            );
        }

        throw new SubscriptionException(
            'You have an existing subscription. Please resume or swap your subscription instead.',
            action: 'subscribe',
            plan: $plan,
            currentState: $user->subscription($user->subscriptionName())?->paddle_status
        );
    }

    private function validatePlanConfig(string $plan, string $action): void
    {
        $planId = config('services.paddle.'.$plan);

        if (blank($planId) || ! is_numeric($planId)) {
            throw new SubscriptionException(
                "The {$plan} plan is not configured. Please contact support.",
                action: $action,
                plan: $plan
            );
        }
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
