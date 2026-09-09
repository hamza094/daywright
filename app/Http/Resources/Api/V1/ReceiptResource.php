<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \Laravel\Paddle\Receipt
 */
#[SchemaName('SubscriptionReceipt')]
class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    #[Override]
    public function toArray($request)
    {
        return [
            /**
             * Receipt identifier.
             *
             * @example 12345
             */
            'id' => $this->id,
            /**
             * Receipt creation timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
            /**
             * ISO 4217 currency code (e.g., USD, EUR).
             *
             * @example USD
             */
            'currency' => $this->currency,
            /**
             * Quantity of items purchased.
             *
             * @example 1
             */
            'quantity' => $this->quantity,
            /**
             * Absolute URL to the receipt PDF.
             *
             * @format uri
             *
             * @example https://daywright.test/storage/receipts/abc123.pdf
             */
            'receipt_url' => $this->receipt_url,
            /**
             * Tax amount as decimal string.
             *
             * @example 2.50
             */
            'tax' => $this->tax,
            /**
             * Total amount as decimal string.
             *
             * @example 12.50
             */
            'amount' => $this->amount,
            /**
             * Receipt update timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
