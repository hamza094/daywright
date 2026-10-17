<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Enums\Meeting\MeetingRecoveryOutcome;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ResolveAmbiguousZoomMeetingManually
{
    public function __construct(
        private FindZoomMeetingForRecovery $finder,
        private FinalizeZoomMeetingRecovery $finalizer,
    ) {}

    public function withZoomMeetingId(Meeting $meeting, int|string $zoomMeetingId): MeetingRecoveryOutcome
    {
        if (! $meeting->awaitsManualZoomRecovery()) {
            return MeetingRecoveryOutcome::Skipped;
        }

        $zoomMeeting = $this->finder->byMeetingId($meeting, $zoomMeetingId);

        return $zoomMeeting instanceof ZoomMeeting
            ? $this->finalizer->execute($meeting, null, $zoomMeeting)
            : MeetingRecoveryOutcome::Unresolved;
    }

    public function markNotCreated(Meeting $meeting): void
    {
        DB::transaction(function () use ($meeting): void {
            $lockedMeeting = Meeting::query()
                ->whereKey($meeting->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMeeting->awaitsManualZoomRecovery()) {
                throw new LogicException('Meeting state changed before manual resolution.');
            }

            $lockedMeeting->transitionTo(MeetingSyncStatus::Failed, 'sync_status');
            $lockedMeeting->update([
                'sync_error' => 'manually_confirmed_not_created',
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);
        });
    }
}
