<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Enums\Meeting\MeetingRecoveryOutcome;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class FinalizeZoomMeetingRecovery
{
    public function execute(
        Meeting $meeting,
        ?string $claimToken,
        ZoomMeeting $zoomMeeting,
    ): MeetingRecoveryOutcome {
        return DB::transaction(function () use ($meeting, $claimToken, $zoomMeeting): MeetingRecoveryOutcome {
            $lockedMeeting = Meeting::query()
                ->whereKey($meeting->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedMeeting instanceof Meeting || ! $this->canFinalize($lockedMeeting, $claimToken)) {
                return MeetingRecoveryOutcome::Skipped;
            }

            if ($lockedMeeting->meeting_id !== null && (string) $lockedMeeting->meeting_id !== (string) $zoomMeeting->meeting_id) {
                $lockedMeeting->update([
                    'sync_error' => 'remote_meeting_id_conflict',
                    'sync_claim_token' => null,
                    'sync_lease_expires_at' => null,
                    'sync_available_at' => null,
                ]);

                Log::warning('Zoom meeting recovery requires manual review', [
                    'meeting_id' => $lockedMeeting->id,
                    'reason' => 'remote_meeting_id_conflict',
                ]);

                return MeetingRecoveryOutcome::ManualReview;
            }

            $lockedMeeting->transitionTo(MeetingSyncStatus::Active, 'sync_status');
            $lockedMeeting->update([
                'meeting_id' => $zoomMeeting->meeting_id,
                'join_url' => $zoomMeeting->join_url,
                'start_url' => $zoomMeeting->start_url,
                'status' => $zoomMeeting->status,
                'synced_at' => now(),
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);

            return MeetingRecoveryOutcome::Recovered;
        });
    }

    private function canFinalize(Meeting $meeting, ?string $claimToken): bool
    {
        if ($claimToken === null) {
            return $meeting->awaitsManualZoomRecovery();
        }

        return $meeting->sync_status === MeetingSyncStatus::CreateUnknown
            && $meeting->sync_claim_token === $claimToken
            && $meeting->sync_lease_expires_at instanceof CarbonInterface
            && $meeting->sync_lease_expires_at->isFuture();
    }
}
