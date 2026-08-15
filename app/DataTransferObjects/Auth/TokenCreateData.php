<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class TokenCreateData
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public string $name,
        public array $scopes,
        public ?\Carbon\Carbon $expires_at = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = Arr::only($payload, ['name', 'scopes', 'expires_at']);
        $data['expires_at'] = empty($data['expires_at']) ? null : \Carbon\Carbon::parse($data['expires_at']);

        if (! isset($data['name']) || $data['name'] === '') {
            throw new InvalidArgumentException('Name is required');
        }

        if (! isset($data['scopes']) || ! is_array($data['scopes']) || empty($data['scopes'])) {
            throw new InvalidArgumentException('Scopes are required');
        }

        return new self(
            name: $data['name'],
            scopes: $data['scopes'],
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
            'scopes' => $this->scopes,
            'expires_at' => $this->expires_at,
        ];
    }
}
