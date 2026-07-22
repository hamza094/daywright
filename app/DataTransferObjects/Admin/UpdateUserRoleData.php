<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

final readonly class UpdateUserRoleData
{
    public function __construct(
        public bool $isAdmin,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $isAdmin = $payload['is_admin'] ?? false;

        // Normalize boolean value (handles string "true"/"false")
        if (is_string($isAdmin)) {
            $isAdmin = filter_var($isAdmin, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return new self(
            isAdmin: (bool) $isAdmin,
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_admin' => $this->isAdmin,
        ];
    }
}
