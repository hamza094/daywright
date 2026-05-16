<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Task;

final readonly class AdminTaskFilters
{
    public function __construct(
        public ?string $search,
        public ?string $state,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            search: is_string($payload['search'] ?? null) ? $payload['search'] : null,
            state: is_string($payload['state'] ?? null) ? mb_strtolower($payload['state']) : null,
        );
    }

    /**
     * @return array{search: ?string, state: ?string}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'state' => $this->state,
        ];
    }
}
