<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Task;

final readonly class UnassignTaskMemberData
{
    public function __construct(
        public int $member,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            member: (int) $validated['member'],
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
            'member' => $this->member,
        ];
    }
}
