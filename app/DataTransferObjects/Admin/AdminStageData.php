<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

final readonly class AdminStageData
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'],
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
            'name' => $this->name,
        ];
    }
}
