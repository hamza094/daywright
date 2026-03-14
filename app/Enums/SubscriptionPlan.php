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

    public function maxProjects(): ?int
    {
        return $this->limit('max_owned_projects');
    }

    public function maxTasksPerProject(): ?int
    {
        return $this->limit('max_active_tasks_per_project');
    }

    public function maxMembersPerProject(): ?int
    {
        return $this->limit('max_members_per_project');
    }

    public function maxCreatedMeetings(): ?int
    {
        return $this->limit('max_created_meetings');
    }

    public function maxApiTokens(): ?int
    {
        return $this->limit('max_api_tokens');
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
