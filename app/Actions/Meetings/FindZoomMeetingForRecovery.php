<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\DataTransferObjects\Zoom\MeetingSummary;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;

final readonly class FindZoomMeetingForRecovery
{
    public function __construct(
        private Zoom $zoom,
    ) {}

    public function byMeetingId(Meeting $meeting, int|string $meetingId): ?ZoomMeeting
    {
        $zoomMeeting = $this->zoom->getMeeting($meetingId, $meeting->user);

        return $zoomMeeting instanceof ZoomMeeting
            && $this->matchesOperationId($zoomMeeting, $meeting->sync_operation_id)
            ? $zoomMeeting
            : null;
    }

    /**
     * @return list<ZoomMeeting>
     */
    public function byOperationId(Meeting $meeting): array
    {
        $matches = [];
        $user = $meeting->user;

        foreach ($this->zoom->listMeetings($user) as $summary) {
            $zoomMeeting = $this->findMatchingMeeting(
                $summary,
                $meeting->sync_operation_id,
                $user,
            );

            if ($zoomMeeting instanceof ZoomMeeting) {
                $matches[] = $zoomMeeting;
            }
        }

        return $matches;
    }

    private function findMatchingMeeting(
        MeetingSummary $summary,
        ?string $operationId,
        User $user,
    ): ?ZoomMeeting {
        if ($summary->hasTrackingFields() && ! $this->matchesTrackingFields($summary->trackingFields, $operationId)) {
            return null;
        }

        $zoomMeeting = $this->zoom->getMeeting($summary->meetingId, $user);

        return $zoomMeeting instanceof ZoomMeeting
            && $this->matchesOperationId($zoomMeeting, $operationId)
            ? $zoomMeeting
            : null;
    }

    private function matchesOperationId(ZoomMeeting $zoomMeeting, ?string $operationId): bool
    {
        return $this->matchesTrackingFields($zoomMeeting->tracking_fields, $operationId);
    }

    /**
     * @param  array<string, string>  $trackingFields
     */
    private function matchesTrackingFields(array $trackingFields, ?string $operationId): bool
    {
        return $operationId !== null
            && ($trackingFields[(string) config('services.zoom.meeting_operation_tracking_field', 'Daywright Operation ID')] ?? null) === $operationId;
    }
}
