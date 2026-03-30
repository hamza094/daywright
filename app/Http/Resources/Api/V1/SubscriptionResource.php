<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\Subscription\SubscriptionPlan;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @param  array<string, array{used: int|null, max: int|null}>  $limits
     * @param  array<int, array{name: string, label: string, interval_label: string, price: int, currency: string, currency_symbol: string, featured: bool}>  $availablePlans
     */
    public function __construct($resource, private readonly SubscriptionPlan $plan, private readonly array $limits, private readonly array $availablePlans = [])
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
        $isBillingVisible = $isBillingSubscribed || $hasGracePeriod;

        return [
            'plan' => $this->plan->value,
            'entitled' => $this->plan === SubscriptionPlan::Pro,
            'subscribed' => $isBillingSubscribed,
            'available_plans' => $this->availablePlans,
            'billing_plan' => $isBillingVisible ? $this->displayBillingPlan() : null,
            'next_payment' => $isBillingSubscribed ? $this->payment() : null,
            'created_at' => $isBillingSubscribed && $subscription !== null
                ? optional($subscription->created_at)->diffForHumans()
                : null,
            'receipts' => ReceiptResource::collection($this->whenLoaded('receipts')),
            'trial' => [
                'active' => $this->isOnTrial(),
                'ends_at' => $this->isOnTrial() ? $this->resolveTrialEndsAt() : null,
            ],

            'grace_period' => [
                'active' => $hasGracePeriod,
                'ends_at' => $hasGracePeriod && $subscription !== null
                    ? optional($subscription->ends_at)->isoFormat('MMMM Do YYYY')
                    : null,
            ],

            'limits' => $this->limits,
        ];
    }

    private function resolveTrialEndsAt(): ?string
    {
        $endsAt = $this->trialEndsAt($this->subscriptionName()) ?? $this->trialEndsAt();

        return optional($endsAt)->isoFormat('MMMM Do YYYY');
    }
}
