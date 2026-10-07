<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use RuntimeException;

/**
 * The facts captured before reading Zoom.
 *
 * They let the locked write verify that the Zoom response still belongs to
 * the same local operation.
 */
final readonly class MeetingUpdateWebhookContext
{
    public function __construct(
        public bool $needsZoomCheck,
        public ?ZoomMeeting $zoomMeeting,
        public ?string $operationId,
        public ?float $reconcileBefore,
    ) {}

    public function isMissingAtZoom(): bool
    {
        return $this->needsZoomCheck && $this->zoomMeeting === null;
    }

    public function zoomMeetingOrFail(): ZoomMeeting
    {
        if (! $this->zoomMeeting instanceof ZoomMeeting) {
            throw new RuntimeException('Zoom meeting snapshot is required for webhook reconciliation.');
        }

        return $this->zoomMeeting;
    }
}
