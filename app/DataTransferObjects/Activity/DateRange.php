<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Activity;

use Carbon\Carbon;

final readonly class DateRange
{
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            startDate: Carbon::parse($payload['start_date'])->startOfDay(),
            endDate: Carbon::parse($payload['end_date'])->endOfDay(),
        );
    }
}
