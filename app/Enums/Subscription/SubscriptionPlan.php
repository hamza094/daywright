<?php

declare(strict_types=1);

namespace App\Enums\Subscription;

use App\Models\User;

/**
 * Maps billing state to the application plan tiers and configured limits.
 */
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
     * @return array{
     *     max_owned_projects?: int|null,
     *     max_active_tasks_per_project?: int|null,
     *     max_members_per_project?: int|null,
     *     max_created_meetings?: int|null,
     *     max_api_tokens?: int|null
     * }
     */
    public function limits(): array
    {
        return config("plan-limits.{$this->value}", []);
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
