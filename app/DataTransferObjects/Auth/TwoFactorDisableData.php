<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class TwoFactorDisableData
{
    public function __construct() {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self;
    }

    /**
     * Convert the DTO to the exact array shape expected by the API / Database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
