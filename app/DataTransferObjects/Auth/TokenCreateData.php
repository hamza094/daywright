<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use Illuminate\Support\Arr;

final readonly class TokenCreateData
{
    public function __construct(
        public ?string $name,
        public ?\Carbon\Carbon $expires_at,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = Arr::only($payload, ['name', 'expires_at']);
        $data['expires_at'] = empty($data['expires_at']) ? null : \Carbon\Carbon::parse($data['expires_at']);

        return new self(
            name: $data['name'] ?? null,
            expires_at: $data['expires_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'expires_at' => $this->expires_at,
        ];
    }
}
