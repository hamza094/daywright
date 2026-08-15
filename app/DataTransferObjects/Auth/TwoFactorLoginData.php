<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class TwoFactorLoginData
{
    public function __construct(
        public string $code,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            code: $validated['code'],
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
            'code' => $this->code,
        ];
    }
}
