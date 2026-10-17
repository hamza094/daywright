<?php

declare(strict_types=1);

namespace App\Actions\Meetings\Concerns;

use App\Models\Meeting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait MeetingOperationValidation
{
    abstract protected function lockMeeting(Meeting $meeting): Meeting;

    /**
     * @template T
     *
     * @param  callable(Meeting): T  $callback
     * @return T
     */
    protected function executeWithOperationIdCheck(Meeting $meeting, string $operationId, string $context, callable $callback, int $transactionRetryAttempts = 5): mixed
    {
        return DB::transaction(function () use ($meeting, $operationId, $context, $callback): mixed {
            $lockedMeeting = $this->lockMeeting($meeting);

            if ($lockedMeeting->sync_operation_id !== $operationId) {
                Log::info('Operation ID changed, skipping '.$context, [
                    'meeting_id' => $meeting->id,
                    'expected_operation_id' => $operationId,
                    'current_operation_id' => $lockedMeeting->sync_operation_id,
                ]);

                return $lockedMeeting->refresh();
            }

            return $callback($lockedMeeting);
        }, attempts: $transactionRetryAttempts);
    }
}
