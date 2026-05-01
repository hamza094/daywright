<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

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
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'receipt_url' => $this->receipt_url,
            'tax' => $this->tax,
            'amount' => $this->amount,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
