<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

final readonly class TaskBulkDeleteData
{
    /**
     * @param  array<int>  $taskIds
     */
    public function __construct(
        public array $taskIds,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            taskIds: array_map('intval', $validated['task_ids']),
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API / Database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task_ids' => $this->taskIds,
        ];
    }
}
