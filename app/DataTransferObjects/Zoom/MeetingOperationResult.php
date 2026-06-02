<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final readonly class MeetingOperationResult
{
    public function __construct(
        public int $meetingId,
        public string $action,
        public int $statusCode,
    ) {}

    public static function updated(int $meetingId, int $statusCode): self
    {
        return new self(
            meetingId: $meetingId,
            action: 'updated',
            statusCode: $statusCode,
        );
    }

    public static function deleted(int $meetingId, int $statusCode): self
    {
        return new self(
            meetingId: $meetingId,
            action: 'deleted',
            statusCode: $statusCode,
        );
    }
}
