<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

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

        return [
            'plan' => $plan->value,

            $this->mergeWhen($this->isSubscribed(), [
                'subscribed' => true,
                'billing_plan' => $this->subscribedPlan(),
                'next_payment' => $this->payment(),
                'created_at' => optional(
                    $this->getSubscription()?->created_at
                )->diffForHumans(),
                'receipts' => ReceiptResource::collection($this->receipts),
            ]),
            $this->mergeWhen(! $this->isSubscribed(), [
                'subscribed' => false,
            ]),

            'trial' => [
                'active' => $this->isOnTrial(),
            ],

            'grace_period' => [
                'active' => $this->hasGracePeriod(),
                'ends_at' => $this->when(
                    $this->hasGracePeriod(),
                    fn () => optional(
                        $this->getSubscription()?->ends_at
                    )->isoFormat('MMMM Do YYYY'),
                ),
            ],

            'downgraded_to_free' => $planLimitService->isDowngradedToFree($this->resource),

            'limits' => $planLimitService->usage($this->resource),
        ];
    }
}
