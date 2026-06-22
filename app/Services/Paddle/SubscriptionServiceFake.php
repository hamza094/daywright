<?php

declare(strict_types=1);

namespace App\Services\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use App\Interfaces\Paddle;
use App\Models\User;
use Override;

final class SubscriptionServiceFake implements Paddle
{
    private array $subscriptions = [];

    private array $invalidPlans = [];

    #[Override]
    public function subscribe(User $user, string $plan): mixed
    {
        $this->validatePlanConfig($plan);

        $key = $user->getKey();

        if ($this->isInvalidPlan($plan)) {
            throw new SubscriptionException('Invalid plan configuration.', action: 'subscribe', plan: $plan);
        }

        if (isset($this->subscriptions[$key])) {
            throw new SubscriptionException(
                'You already have an active subscription.',
                action: 'subscribe',
                plan: $plan,
                currentState: $this->subscriptions[$key]['status']
            );
        }

        $this->subscriptions[$key] = [
            'plan' => $plan,
            'status' => 'active',
        ];

        return 'https://fake-paylink-url.com';
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function swap(User $user, string $plan): array
    {
        $this->validatePlanConfig($plan);

        $key = $user->getKey();

        if ($this->isInvalidPlan($plan)) {
            throw new SubscriptionException('Invalid plan configuration.', action: 'swap', plan: $plan);
        }

        if (! isset($this->subscriptions[$key])) {
            throw new SubscriptionException('You are not subscribed to a paid plan.', action: 'swap', plan: $plan);
        }

        $this->subscriptions[$key]['plan'] = $plan;

        return [
            'message' => 'Your subscription has been successfully updated to the '.$plan.' plan (fake).',
        ];
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function cancel(User $user, string $plan): array
    {
        $key = $user->getKey();

        if (! isset($this->subscriptions[$key]) || $this->subscriptions[$key]['status'] === 'canceled') {
            return [
                'message' => 'Your subscription has been canceled successfully (fake).',
            ];
        }

        $this->subscriptions[$key]['status'] = 'canceled';

        return [
            'message' => 'Your subscription has been canceled successfully (fake).',
        ];
    }

    public function setState(User $user, string $status): void
    {
        $key = $user->getKey();

        if (! isset($this->subscriptions[$key])) {
            $this->subscriptions[$key] = [
                'plan' => 'monthly',
                'status' => $status,
            ];
        } else {
            $this->subscriptions[$key]['status'] = $status;
        }
    }

    public function setInvalidPlan(string $plan): void
    {
        $this->invalidPlans[] = $plan;
    }

    public function clearSubscriptions(): void
    {
        $this->subscriptions = [];
    }

    private function isInvalidPlan(string $plan): bool
    {
        return in_array($plan, $this->invalidPlans, true);
    }

    private function validatePlanConfig(string $plan): void
    {
        $planId = config('services.paddle.'.$plan);

        if (blank($planId) || ! is_numeric($planId)) {
            throw new SubscriptionException(
                "The {$plan} plan is not configured. Please contact support.",
                action: 'subscribe',
                plan: $plan,
                currentState: null
            );
        }
    }
}
