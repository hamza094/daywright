<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Subscription;

use App\Models\SubscriptionOperation;

final readonly class SubscriptionOperationResult
{
    public function __construct(
        public SubscriptionOperation $operation,
        public ?string $message = null,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     status: string,
     *     target_plan: string|null,
     *     available_at: string|null,
     *     message?: string
     * }
     */
    public function toResponseData(): array
    {
        $data = [
            'id' => $this->operation->operation_uuid,
            'type' => $this->operation->type->value,
            'status' => $this->operation->status->value,
            'target_plan' => $this->operation->target_plan,
            'available_at' => $this->operation->available_at?->toISOString(),
        ];

        if ($this->message !== null) {
            $data['message'] = $this->message;
        }

        return $data;
    }
}
