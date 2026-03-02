<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

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
        return [
            $this->mergeWhen($this->isSubscribed(), [
                'subscribed' => true,
                'plan' => $this->subscribedPlan(),
                'next_payment' => $this->payment(),
                'created_at' => optional(
                    $this->getSubscription()?->created_at
                )->diffForHumans(),
                'receipts' => ReceiptResource::collection($this->receipts),
            ]),
            $this->mergeWhen(! $this->isSubscribed(), [
                'subscribed' => false,
            ]),

            $this->mergeWhen($this->hasGracePeriod(), [
                'grace_period' => true,

                'grace_period_ends_at' => optional(
                    $this->getSubscription()?->ends_at
                )->isoFormat('MMMM Do YYYY'),
            ]),
        ];
    }
}
