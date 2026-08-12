<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class RecoveryCodesData
{
    public function __construct(
        public readonly string $currentPassword
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            currentPassword: $validated['current_password']
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
            'current_password' => $this->currentPassword,
        ];
    }
}
