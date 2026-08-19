<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonInterface;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Paddle\Payment;
use Override;

/**
 * @mixin \Laravel\Paddle\Payment
 */
#[SchemaName('PaymentDetails')]
class PaymentResource extends JsonResource
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
        if (! $this->resource instanceof Payment) {
            return [
                'amount' => null,
                'currency' => null,
                'date' => null,
            ];
        }

        return [
            /**
             * Payment amount in cents (e.g., 1200 = $12.00).
             *
             * @var int
             *
             * @example 1200
             */
            'amount' => $this->resource->amount(),
            /**
             * ISO 4217 currency code (e.g., USD, EUR).
             *
             * @var string
             *
             * @example USD
             */
            'currency' => $this->resource->currency(),
            /**
             * Payment date in UTC ISO 8601 format.
             *
             * @var string|null
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'date' => $this->formatDate($this->resource->date()),
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
