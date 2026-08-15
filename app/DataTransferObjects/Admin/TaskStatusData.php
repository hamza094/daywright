<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

final readonly class TaskStatusData
{
    public function __construct(
        public ?string $label,
        public ?string $color,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            label: $validated['label'] ?? null,
            color: $validated['color'] ?? null,
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API / Database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'color' => $this->color,
        ], fn (?string $value): bool => $value !== null);
    }
}
