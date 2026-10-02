<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

use Illuminate\Support\Arr;

final readonly class ProjectUpdateData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        private array $attributes,
        public ?int $version = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            attributes: Arr::only($payload, ['name', 'about', 'notes']),
            version: isset($payload['version']) && is_numeric($payload['version']) ? (int) $payload['version'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }

    public function version(): ?int
    {
        return $this->version;
    }

    /**
     * Check if the request has only version (no business changes).
     * Version alone is not considered an edit.
     */
    public function isVersionOnly(): bool
    {
        return $this->attributes === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
