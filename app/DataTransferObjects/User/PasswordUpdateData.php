<?php

declare(strict_types=1);

namespace App\DataTransferObjects\User;

final readonly class PasswordUpdateData
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            currentPassword: $validated['current_password'],
            password: $validated['password'],
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
            'current_password' => $this->currentPassword,
            'password' => $this->password,
        ];
    }
}
