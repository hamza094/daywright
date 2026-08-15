<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class LoginCredentialsData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            email: (string) ($payload['email'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
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
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
