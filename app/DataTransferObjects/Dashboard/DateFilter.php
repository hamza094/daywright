<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

final readonly class DateFilter
{
    public function __construct(
        public ?int $year,
        public ?int $month,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            year: is_numeric($payload['year'] ?? null) ? (int) $payload['year'] : null,
            month: is_numeric($payload['month'] ?? null) ? (int) $payload['month'] : null,
        );
    }
}
