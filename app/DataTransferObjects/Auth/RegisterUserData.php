<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            email: (string) ($payload['email'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
        );
    }

    /**
     * @return array{name: string, email: string, password: string}
     */
    public function toUserAttributes(string $hashedPassword): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $hashedPassword,
        ];
    }
}
