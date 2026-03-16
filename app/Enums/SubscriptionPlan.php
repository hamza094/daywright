<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

enum SubscriptionPlan: string
{
    case Free = 'free';
    case Pro = 'pro';

    public static function fromUser(User $user): self
    {
        if ($user->isOnTrial()) {
            return self::Pro;
        }

        $subscription = $user->getSubscription();

        if ($subscription !== null && $subscription->valid()) {
            return self::Pro;
        }

        return self::Free;
    }

    /**
     * @return array<string, mixed>
     */
    public function limits(): array
    {
        /** @var array<string, mixed> $limits */
        $limits = config("plan-limits.{$this->value}", []);

        return $limits;
    }

    public function maxFor(PlanLimitType $type): ?int
    {
        return $this->limit($type->configKey());
    }

    public function hasFeature(string $feature): bool
    {
        /** @var array<int, string> $features */
        $features = $this->limits()['features'] ?? [];

        return in_array($feature, $features, true);
    }

    private function limit(string $key): ?int
    {
        $value = $this->limits()[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
