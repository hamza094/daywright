<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Enums\Meeting\MeetingRecoveryOutcome;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use App\Services\Project\MeetingSyncErrorFormatter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class RecoverAmbiguousZoomMeeting
{
    private const int MAX_ATTEMPTS = 5;

    private const int LEASE_MINUTES = 5;

    private const array BACKOFF_SECONDS = [
        1 => 60,
        2 => 300,
        3 => 900,
        4 => 3600,
    ];

    public function __construct(
        private FindZoomMeetingForRecovery $finder,
        private FinalizeZoomMeetingRecovery $finalizer,
        private MeetingSyncErrorFormatter $errorFormatter,
    ) {}

    public function execute(Meeting $meeting): MeetingRecoveryOutcome
    {
        $claimToken = $this->claim($meeting);

        if ($claimToken === null) {
            return MeetingRecoveryOutcome::Skipped;
        }

        try {
            $matches = $this->findMatches($meeting);

            return match (count($matches)) {
                0 => $this->scheduleRetry($meeting, $claimToken),
                1 => $this->finalizer->execute($meeting, $claimToken, $matches[0]),
                default => $this->requireManualReview($meeting, $claimToken),
            };
        } catch (Throwable $exception) {
            report($exception);

            return $this->scheduleRetry($meeting, $claimToken, $exception);
        }
    }

    /**
     * @return list<ZoomMeeting>
     */
    private function findMatches(Meeting $meeting): array
    {
        if ($meeting->meeting_id !== null) {
            $zoomMeeting = $this->finder->byMeetingId($meeting, $meeting->meeting_id);

            return $zoomMeeting instanceof ZoomMeeting ? [$zoomMeeting] : [];
        }

        return $this->finder->byOperationId($meeting);
    }

    private function claim(Meeting $meeting): ?string
    {
        $now = now();
        $claimToken = Str::uuid()->toString();

        $claimed = Meeting::query()
            ->whereKey($meeting->getKey())
            ->readyForZoomRecoveryAt($now)
            ->where($this->claimAvailable(...))
            ->update($this->claimValues($claimToken, $now));

        return $claimed === 1 ? $claimToken : null;
    }

    /**
     * @param  Builder<Meeting>  $query
     */
    private function claimAvailable(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('sync_claim_token')
                ->orWhere('sync_lease_expires_at', '<=', now());
        });
    }

    /**
     * @return array{sync_status: string, sync_claim_token: string, sync_lease_expires_at: CarbonInterface, sync_available_at: CarbonInterface}
     */
    private function claimValues(string $claimToken, CarbonInterface $now): array
    {
        return [
            'sync_status' => MeetingSyncStatus::CreateUnknown->value,
            'sync_claim_token' => $claimToken,
            'sync_lease_expires_at' => $now->copy()->addMinutes(self::LEASE_MINUTES),
            'sync_available_at' => $now,
        ];
    }

    private function scheduleRetry(Meeting $meeting, string $claimToken, ?Throwable $exception = null): MeetingRecoveryOutcome
    {
        return DB::transaction(function () use ($meeting, $claimToken, $exception): MeetingRecoveryOutcome {
            $lockedMeeting = $this->lockClaimedMeeting($meeting, $claimToken);

            if (! $lockedMeeting instanceof Meeting) {
                return MeetingRecoveryOutcome::Skipped;
            }

            $nextAttempt = $lockedMeeting->sync_attempts + 1;

            if ($nextAttempt >= self::MAX_ATTEMPTS) {
                return $this->setManualReview($lockedMeeting, 'manual_review_required', $nextAttempt);
            }

            $lockedMeeting->update([
                'sync_attempts' => $nextAttempt,
                'sync_error' => $exception instanceof Throwable ? $this->errorFormatter->format($exception) : 'zoom_meeting_not_found',
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => now()->addSeconds(self::BACKOFF_SECONDS[$nextAttempt]),
            ]);

            return MeetingRecoveryOutcome::Unresolved;
        });
    }

    private function requireManualReview(
        Meeting $meeting,
        string $claimToken,
        ?int $attempts = null,
    ): MeetingRecoveryOutcome {
        return DB::transaction(function () use ($meeting, $claimToken, $attempts): MeetingRecoveryOutcome {
            $lockedMeeting = $this->lockClaimedMeeting($meeting, $claimToken);

            if (! $lockedMeeting instanceof Meeting) {
                return MeetingRecoveryOutcome::Skipped;
            }

            $lockedMeeting->update([
                'sync_attempts' => $attempts ?? $lockedMeeting->sync_attempts,
                'sync_error' => 'multiple_exact_operation_matches',
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
            ]);

            Log::warning('Zoom meeting recovery requires manual review', [
                'meeting_id' => $lockedMeeting->id,
                'reason' => $lockedMeeting->sync_error,
            ]);

            return MeetingRecoveryOutcome::ManualReview;
        });
    }

    private function setManualReview(Meeting $meeting, string $reason, int $attempts): MeetingRecoveryOutcome
    {
        $meeting->update([
            'sync_attempts' => $attempts,
            'sync_error' => $reason,
            'sync_claim_token' => null,
            'sync_lease_expires_at' => null,
            'sync_available_at' => null,
        ]);

        Log::warning('Zoom meeting recovery requires manual review', [
            'meeting_id' => $meeting->id,
            'reason' => $reason,
        ]);

        return MeetingRecoveryOutcome::ManualReview;
    }

    private function lockClaimedMeeting(Meeting $meeting, string $claimToken): ?Meeting
    {
        $lockedMeeting = Meeting::query()
            ->whereKey($meeting->getKey())
            ->lockForUpdate()
            ->first();

        return $lockedMeeting instanceof Meeting && $this->ownsLiveClaim($lockedMeeting, $claimToken)
            ? $lockedMeeting
            : null;
    }

    private function ownsLiveClaim(Meeting $meeting, string $claimToken): bool
    {
        return $meeting->sync_status === MeetingSyncStatus::CreateUnknown
            && $meeting->sync_claim_token === $claimToken
            && $meeting->sync_lease_expires_at instanceof CarbonInterface
            && $meeting->sync_lease_expires_at->isFuture();
    }
}
