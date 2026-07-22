<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class InvitationData
{
    public function __construct(
        public string $email,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            email: (string) ($payload['email'] ?? ''),
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
        ];
    }
}
