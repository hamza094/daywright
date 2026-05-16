<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Task;

final readonly class UserTaskFilters
{
    public function __construct(
        public bool $userCreated,
        public bool $taskAssigned,
        public bool $completed,
        public bool $overdue,
        public bool $remaining,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            userCreated: (bool) ($payload['user_created'] ?? false),
            taskAssigned: (bool) ($payload['task_assigned'] ?? false),
            completed: (bool) ($payload['completed'] ?? false),
            overdue: (bool) ($payload['overdue'] ?? false),
            remaining: (bool) ($payload['remaining'] ?? false),
        );
    }

    public function hasAnyFilter(): bool
    {
        return $this->userCreated
            || $this->taskAssigned
            || $this->completed
            || $this->overdue
            || $this->remaining;
    }

    /**
     * @return array{user_created: bool, task_assigned: bool, completed: bool, overdue: bool, remaining: bool}
     */
    public function toArray(): array
    {
        return [
            'user_created' => $this->userCreated,
            'task_assigned' => $this->taskAssigned,
            'completed' => $this->completed,
            'overdue' => $this->overdue,
            'remaining' => $this->remaining,
        ];
    }
}
