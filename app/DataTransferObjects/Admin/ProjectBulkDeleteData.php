<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

final readonly class ProjectBulkDeleteData
{
    /**
     * @param  array<int>  $projectIds
     */
    public function __construct(
        public array $projectIds,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            projectIds: array_map('intval', $validated['project_ids']),
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
            'project_ids' => $this->projectIds,
        ];
    }
}
