<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Jobs\RecoverZoomMeetingOperationJob;
use App\Models\Meeting;
use App\Services\Zoom\ZoomRecoveryPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class RecoverPendingZoomMeetingOperations
{
    private const int LEASE_MINUTES = 5;

    public function __construct(
        private ZoomRecoveryPolicy $policy,
    ) {}

    public function execute(): bool
    {
        $meeting = $this->claimNextDueOperation();

        if ($meeting === null) {
            return false;
        }

        RecoverZoomMeetingOperationJob::dispatch(
            $meeting->id,
            $meeting->sync_operation_id,
            $meeting->sync_claim_token,
        );

        Log::info('Dispatched recovery job for meeting', [
            'meeting_id' => $meeting->id,
            'operation_type' => $meeting->sync_operation_type,
        ]);

        return true;
    }

    private function claimNextDueOperation(): ?Meeting
    {
        $now = now();
        $claimToken = Str::uuid()->toString();

        return DB::transaction(function () use ($now, $claimToken): ?Meeting {
            $meeting = Meeting::query()
                ->readyForUpdateDeleteRecoveryAt($now)
                ->where(function ($query) use ($now): void {
                    $query->whereNull('sync_claim_token')
                        ->orWhereNull('sync_lease_expires_at')
                        ->orWhere('sync_lease_expires_at', '<=', $now);
                })
                ->lockForUpdate()
                ->orderBy('sync_available_at')
                ->first();

            if ($meeting === null) {
                return null;
            }

            if (! $this->isClaimable($meeting)) {
                return null;
            }

            $meeting->update([
                'sync_claim_token' => $claimToken,
                'sync_lease_expires_at' => $now->copy()->addMinutes(self::LEASE_MINUTES),
            ]);

            return $meeting;
        });
    }

    private function isClaimable(Meeting $meeting): bool
    {
        return $meeting->sync_claim_token === null
            || $this->policy->shouldSkipExpiredLease($meeting, now());
    }
}
