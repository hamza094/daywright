<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use Illuminate\Support\Arr;

final readonly class TokenCreateData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private array $attributes) {}

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = Arr::only($payload, ['name', 'expires_at']);
        $data['expires_at'] = empty($data['expires_at']) ? null : \Carbon\Carbon::parse($data['expires_at']);

        return new self(attributes: $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
