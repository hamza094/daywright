<?php

declare(strict_types=1);

namespace App\QueryBuilder;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Meeting>
 */
class MeetingBuilder extends Builder
{
    public function createUnknownDueAt(CarbonInterface $at): self
    {
        return $this->where('sync_status', MeetingSyncStatus::CreateUnknown)
            ->where('sync_available_at', '<=', $at);
    }

    public function creatingWithExpiredLeaseAt(CarbonInterface $at): self
    {
        return $this->where('sync_status', MeetingSyncStatus::Creating)
            ->where('sync_lease_expires_at', '<=', $at);
    }

    public function readyForZoomRecoveryAt(CarbonInterface $at): self
    {
        return $this->where(function (Builder $query) use ($at): void {
            $query->creatingWithExpiredLeaseAt($at)
                ->orWhere(fn (Builder $query): Builder => $query->createUnknownDueAt($at));
        });
    }

    public function updatingWithExpiredLeaseAt(CarbonInterface $at): self
    {
        return $this->where('sync_status', MeetingSyncStatus::Updating)
            ->where(function (Builder $query) use ($at): void {
                $query->where('sync_lease_expires_at', '<=', $at)
                    ->orWhere('sync_available_at', '<=', $at);
            });
    }

    public function deletingWithExpiredLeaseAt(CarbonInterface $at): self
    {
        return $this->where('sync_status', MeetingSyncStatus::Deleting)
            ->where(function (Builder $query) use ($at): void {
                $query->where('sync_lease_expires_at', '<=', $at)
                    ->orWhere('sync_available_at', '<=', $at);
            });
    }

    public function updateFailedDueAt(CarbonInterface $at): self
    {
        return $this->where('sync_status', MeetingSyncStatus::UpdateFailed)
            ->where('sync_available_at', '<=', $at);
    }

    public function deleteFailedDueAt(CarbonInterface $at): self
    {
        return $this->where('sync_status', MeetingSyncStatus::DeleteFailed)
            ->where('sync_available_at', '<=', $at);
    }

    public function readyForUpdateRecoveryAt(CarbonInterface $at): self
    {
        return $this->where(function (Builder $query) use ($at): void {
            $query->updatingWithExpiredLeaseAt($at)
                ->orWhere(fn (Builder $query): Builder => $query->updateFailedDueAt($at));
        });
    }

    public function readyForDeleteRecoveryAt(CarbonInterface $at): self
    {
        return $this->where(function (Builder $query) use ($at): void {
            $query->deletingWithExpiredLeaseAt($at)
                ->orWhere(fn (Builder $query): Builder => $query->deleteFailedDueAt($at));
        });
    }

    public function readyForAnyRecoveryAt(CarbonInterface $at): self
    {
        return $this->where(function (Builder $query) use ($at): void {
            $query->readyForZoomRecoveryAt($at)
                ->orWhere(fn (Builder $query): Builder => $query->readyForUpdateRecoveryAt($at))
                ->orWhere(fn (Builder $query): Builder => $query->readyForDeleteRecoveryAt($at));
        });
    }

    public function readyForUpdateDeleteRecoveryAt(CarbonInterface $at): self
    {
        return $this->where(function (Builder $query) use ($at): void {
            $query->readyForUpdateRecoveryAt($at)
                ->orWhere(fn (Builder $query): Builder => $query->readyForDeleteRecoveryAt($at));
        });
    }
}
