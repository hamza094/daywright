<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\Subscription\SubscriptionPlan;
use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Paddle\Payment;
use Override;

/**
 * @mixin \App\Models\User
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @param  array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>  $limits
     * @param  array<int, array{name: string, label: string, interval_label: string, price: int, currency: string, currency_symbol: string, featured: bool}>  $availablePlans
     * @param  array{subscribed: bool, billing_plan: ?string, next_payment: ?Payment, created_at: ?CarbonInterface}  $billing
     * @param  array{active: bool, ends_at: ?CarbonInterface}  $trial
     * @param  array{active: bool, ends_at: ?CarbonInterface}  $gracePeriod
     */
    public function __construct(
        $resource,
        private readonly SubscriptionPlan $plan,
        private readonly array $limits,
        private readonly array $availablePlans,
        private readonly array $billing,
        private readonly array $trial,
        private readonly array $gracePeriod,
    ) {
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
        return [
            'plan' => $this->plan->value,
            'entitled' => $this->plan === SubscriptionPlan::Pro,
            'subscribed' => $this->billing['subscribed'],
            'available_plans' => $this->availablePlans,
            'billing_plan' => $this->billing['billing_plan'],
            'next_payment' => $this->billing['next_payment'],
            'created_at' => $this->formatDate($this->billing['created_at'], true),
            'receipts' => ReceiptResource::collection($this->whenLoaded('receipts')),
            'trial' => [
                'active' => $this->trial['active'],
                'ends_at' => $this->formatDate($this->trial['ends_at']),
            ],

            'grace_period' => [
                'active' => $this->gracePeriod['active'],
                'ends_at' => $this->formatDate($this->gracePeriod['ends_at']),
            ],

            'limits' => $this->limits,
        ];
    }

    /**
     * @return array{iso: string, human: string}|null
     */
    private function formatDate(?CarbonInterface $date, bool $relative = false): ?array
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return [
            'iso' => $date->toIso8601String(),
            'human' => $relative ? $date->diffForHumans() : $date->isoFormat('MMMM Do YYYY'),
        ];
    }
}
