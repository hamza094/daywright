<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Task;

final readonly class AdminTaskFilters
{
    public function __construct(
        public ?string $search,
        public ?string $state,
        public ?string $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            search: is_string($payload['search'] ?? null) ? $payload['search'] : null,
            state: is_string($payload['state'] ?? null) ? mb_strtolower($payload['state']) : null,
            sort: is_string($payload['sort'] ?? null) ? $payload['sort'] : null,
        );
    }

    /**
     * @return array{search: ?string, state: ?string, sort: ?string}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'state' => $this->state,
            'sort' => $this->sort,
        ];
    }
}
