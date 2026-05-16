<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class DashboardProjectFilters
{
    public function __construct(
        public ?string $search,
        public bool $member,
        public bool $abandoned,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            search: is_string($payload['search'] ?? null) ? $payload['search'] : null,
            member: (bool) ($payload['member'] ?? false),
            abandoned: (bool) ($payload['abandoned'] ?? false),
        );
    }

    /**
     * @return array{search: ?string, member: bool, abandoned: bool}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'member' => $this->member,
            'abandoned' => $this->abandoned,
        ];
    }
}
