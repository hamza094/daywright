<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\SubscriptionPlan;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @param  array<string, array{used: int|null, max: int|null}>  $limits
     */
    public function __construct($resource, private readonly SubscriptionPlan $plan, private readonly array $limits)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $subscription = $this->getSubscription();
        $isBillingSubscribed = $subscription?->recurring() === true;
        $hasGracePeriod = $this->hasGracePeriod();

        return [
            'plan' => $this->plan->value,
            'entitled' => $this->plan === SubscriptionPlan::Pro,
            'subscribed' => $isBillingSubscribed,

            $this->mergeWhen($isBillingSubscribed || $hasGracePeriod, [
                'billing_plan' => $this->resource->displayBillingPlan(),
            ]),

            $this->mergeWhen($isBillingSubscribed, fn () => [
                'next_payment' => $this->whenNotNull($this->payment()),
                'created_at' => optional($subscription?->created_at)->diffForHumans(),
            ]),

            $this->mergeWhen($this->receipts->isNotEmpty(), fn () => [
                'receipts' => ReceiptResource::collection($this->receipts),
            ]),

            $this->mergeWhen($this->isOnTrial(), fn () => [
                'trial' => [
                    'active' => true,
                    'ends_at' => $this->resolveTrialEndsAt(),
                ],
            ]),

            $this->mergeWhen($hasGracePeriod, [
                'grace_period' => [
                    'active' => true,
                    'ends_at' => optional($subscription?->ends_at)->isoFormat('MMMM Do YYYY'),
                ],
            ]),

            'limits' => $this->limits,
        ];
    }

    private function resolveTrialEndsAt(): ?string
    {
        $endsAt = $this->trialEndsAt($this->subscriptionName()) ?? $this->trialEndsAt();

        return optional($endsAt)->isoFormat('MMMM Do YYYY');
    }
}
