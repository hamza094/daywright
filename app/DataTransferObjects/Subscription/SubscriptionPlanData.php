<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Subscription;

final readonly class SubscriptionPlanData
{
    public function __construct(
        public string $plan,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            plan: trim((string) ($payload['plan'] ?? '')),
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
        ];
    }
}
