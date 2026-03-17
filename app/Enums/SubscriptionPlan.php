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
        if ($user->isOnTrial() || $user->isSubscribed()) {
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

    private function limit(string $key): ?int
    {
        $value = $this->limits()[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
