<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class AdminProjectFilters
{
    public function __construct(
        public ?string $search,
        public ?string $state,
        public ?string $status,
        public ?string $from,
        public ?string $to,
        public ?int $stage,
        public bool $members,
        public bool $tasks,
        public ?string $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $stage = $payload['stage'] ?? null;

        return new self(
            search: is_string($payload['search'] ?? null) ? $payload['search'] : null,
            state: is_string($payload['state'] ?? null) ? mb_strtolower($payload['state']) : null,
            status: is_string($payload['status'] ?? null) ? mb_strtolower($payload['status']) : null,
            from: is_string($payload['from'] ?? null) ? $payload['from'] : null,
            to: is_string($payload['to'] ?? null) ? $payload['to'] : null,
            stage: is_numeric($stage) ? (int) $stage : null,
            members: (bool) ($payload['members'] ?? false),
            tasks: (bool) ($payload['tasks'] ?? false),
            sort: is_string($payload['sort'] ?? null) ? $payload['sort'] : null,
        );
    }

    /**
     * @return array{search: ?string, state: ?string, status: ?string, from: ?string, to: ?string, stage: ?int, members: bool, tasks: bool, sort: ?string}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'state' => $this->state,
            'status' => $this->status,
            'from' => $this->from,
            'to' => $this->to,
            'stage' => $this->stage,
            'members' => $this->members,
            'tasks' => $this->tasks,
            'sort' => $this->sort,
        ];
    }
}
