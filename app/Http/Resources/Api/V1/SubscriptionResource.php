<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\SubscriptionPlan;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\User
 */
class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $planLimitService = app(PlanLimitService::class);
        $plan = $planLimitService->plan($this->resource);
        $subscription = $this->getSubscription();
        $isBillingSubscribed = $subscription?->recurring() === true;
        $hasGracePeriod = $this->hasGracePeriod();

        return [
            'plan' => $plan->value,
            'entitled' => $plan === SubscriptionPlan::Pro,
            'subscribed' => $isBillingSubscribed,

            $this->mergeWhen($isBillingSubscribed || $hasGracePeriod, [
                'billing_plan' => $this->resolveSubscriptionPlan($subscription?->paddle_plan),
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

            'limits' => $planLimitService->usage($this->resource),
        ];
    }

    private function resolveTrialEndsAt(): ?string
    {
        $endsAt = $this->trialEndsAt($this->subscriptionName()) ?? $this->trialEndsAt();

        return optional($endsAt)->isoFormat('MMMM Do YYYY');
    }

    private function resolveSubscriptionPlan(null|int|string $paddlePlan): string
    {
        $monthlyPlanId = (int) config('services.paddle.monthly');
        $yearlyPlanId = (int) config('services.paddle.yearly');

        return match ((int) $paddlePlan) {
            $monthlyPlanId => 'monthly',
            $yearlyPlanId => 'yearly',
            default => 'Unknown',
        };
    }
}
