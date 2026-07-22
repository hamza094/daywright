<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class VerificationData
{
    public function __construct(
        public string $hash,
        public bool $hasValidSignature,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            hash: $validated['hash'],
            hasValidSignature: $validated['hasValidSignature'],
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
            'hash' => $this->hash,
            'hasValidSignature' => $this->hasValidSignature,
        ];
    }
}
