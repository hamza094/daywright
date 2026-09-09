<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\Subscription\SubscriptionPlan;
use Carbon\CarbonInterface;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Paddle\Payment;
use Override;

/**
 * @mixin \App\Models\User
 */
#[SchemaName('SubscriptionDetails')]
class SubscriptionResource extends JsonResource
{
    /**
     * @param  array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>  $limits
     * @param  array<int, array{name: string, label: string, interval_label: string, price: int, currency: string, currency_symbol: string, featured: bool}>  $availablePlans
     * @param  array{subscribed: bool, billing_status: ?string, billing_plan: ?string, next_payment: ?Payment, created_at: ?CarbonInterface}  $billing
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
            /**
             * Effective account plan.
             *
             * @example free
             */
            'plan' => $this->plan->value,
            /**
             * Indicates whether the account is currently entitled to Pro limits.
             *
             * @example false
             */
            'entitled' => $this->plan === SubscriptionPlan::Pro,
            /**
             * Indicates whether the billing subscription is currently active.
             *
             * @example false
             */
            'subscribed' => $this->billing['subscribed'],
            /**
             * Raw billing status from Paddle (active, trialing, past_due, paused, canceled, etc.).
             *
             * @example active
             */
            'billing_status' => $this->billing['billing_status'],
            /**
             * Plans the user can purchase or switch to.
             *
             * @var array<int, array{name: string, label: string, interval_label: string, price: int, currency: string, currency_symbol: string, featured: bool}>
             *
             * @example [{"name":"monthly","label":"Monthly","interval_label":"month","price":12,"currency":"USD","currency_symbol":"$","featured":true}]
             */
            'available_plans' => $this->availablePlans,
            /**
             * Active billing cadence when the account has an active or grace-period subscription.
             *
             * @example monthly
             */
            'billing_plan' => $this->billing['billing_plan'],
            /**
             * Upcoming payment details from Paddle when billing is active.
             */
            'next_payment' => $this->billing['next_payment']
                ? new PaymentResource($this->billing['next_payment'])
                : null,
            /**
             * Subscription creation timestamp in UTC ISO 8601 format when billing is active.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'created_at' => $this->formatDate($this->billing['created_at']),
            /**
             * Receipt history for the account.
             */
            'receipts' => ReceiptResource::collection($this->whenLoaded('receipts')),
            /**
             * Trial status information.
             *
             * @example {"active":false,"ends_at":null}
             */
            'trial' => [
                'active' => $this->trial['active'],
                'ends_at' => $this->formatDate($this->trial['ends_at']),
            ],

            /**
             * Grace-period status information.
             *
             * @example {"active":false,"ends_at":null}
             */
            'grace_period' => [
                /** @var bool */
                'active' => $this->gracePeriod['active'],
                'ends_at' => $this->formatDate($this->gracePeriod['ends_at']),
            ],

            /**
             * Current plan usage and maximums for tracked limits.
             *
             * @var array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>
             */
            'limits' => $this->limits,
        ];
    }

    private function formatDate(?CarbonInterface $date): ?string
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->setTimezone('UTC')->toIso8601String();
    }
}
