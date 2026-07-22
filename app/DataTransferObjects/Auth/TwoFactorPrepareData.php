<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class TwoFactorPrepareData
{
    public function __construct(
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            password: $validated['password'],
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
            'password' => $this->password,
        ];
    }
}
