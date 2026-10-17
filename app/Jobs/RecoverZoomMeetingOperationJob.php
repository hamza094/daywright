<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Meetings\PerformZoomMeetingRecovery;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class RecoverZoomMeetingOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public function __construct(
        private readonly int $meetingId,
        private readonly string $operationId,
        private readonly string $claimToken,
    ) {}

    public function handle(Zoom $zoom, PerformZoomMeetingRecovery $recovery): void
    {
        $meeting = Meeting::query()->find($this->meetingId);

        if ($meeting === null) {
            Log::warning('Meeting not found for recovery job', [
                'meeting_id' => $this->meetingId,
            ]);

            return;
        }

        $recovery->execute(
            $meeting,
            $this->operationId,
            $this->claimToken,
            $zoom,
        );
    }
}
