<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notification;

final readonly class NotificationStatusUpdateData
{
    public function __construct(
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            status: $validated['status'],
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API / Database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
        ];
    }
}
