<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Task;

final readonly class AssignTaskMembersData
{
    /**
     * @param  array<int, int>  $members
     */
    public function __construct(
        public array $members,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            members: array_map('intval', $validated['members']),
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
            'members' => $this->members,
        ];
    }
}
