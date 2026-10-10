<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Meetings\ResolveAmbiguousZoomMeetingManually;
use App\Enums\Meeting\MeetingRecoveryOutcome;
use App\Models\Meeting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResolveAmbiguousZoomMeeting extends Command
{
    protected $signature = 'meetings:resolve-ambiguous
        {meeting_id : The local meeting ID}
        {--zoom-meeting-id= : The verified Zoom meeting ID}
        {--mark-failed : Confirm that Zoom did not create the meeting}
        {--reference= : Required incident or ticket reference}
        {--force : Skip the interactive confirmation}';

    protected $description = 'Manually resolve a Zoom meeting that requires review';

    public function __construct(
        private readonly ResolveAmbiguousZoomMeetingManually $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $meetingId = (int) $this->argument('meeting_id');
        $zoomMeetingId = $this->option('zoom-meeting-id');
        $markFailed = (bool) $this->option('mark-failed');
        $reference = $this->option('reference');
        $hasZoomMeetingId = is_string($zoomMeetingId) && $zoomMeetingId !== '';

        if (! is_string($reference) || $reference === '') {
            $this->error('A ticket or incident reference is required.');

            return self::FAILURE;
        }

        if ($hasZoomMeetingId === $markFailed) {
            $this->error('Provide exactly one of --zoom-meeting-id or --mark-failed.');

            return self::FAILURE;
        }

        $meeting = Meeting::find($meetingId);

        if (! $meeting instanceof Meeting) {
            $this->error("Meeting not found: {$meetingId}");

            return self::FAILURE;
        }

        if (! $meeting->awaitsManualZoomRecovery()) {
            $this->error('Meeting is not awaiting manual recovery.');

            return self::FAILURE;
        }

        if (! (bool) $this->option('force') && ! $this->confirm("Resolve meeting {$meetingId} with reference {$reference}?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            if ($hasZoomMeetingId) {
                return $this->activate($meeting, $zoomMeetingId, $reference);
            }

            return $this->markFailed($meeting, $reference);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Manual Zoom meeting resolution failed', [
                'meeting_id' => $meetingId,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            $this->error('Resolution failed. The meeting remains recoverable.');

            return self::FAILURE;
        }
    }

    private function activate(Meeting $meeting, string $zoomMeetingId, string $reference): int
    {
        $outcome = $this->resolver->withZoomMeetingId($meeting, $zoomMeetingId);

        if ($outcome !== MeetingRecoveryOutcome::Recovered) {
            $this->error('Zoom meeting could not be verified for this operation.');

            return self::FAILURE;
        }

        Log::info('Manual Zoom meeting resolution completed', [
            'meeting_id' => $meeting->id,
            'resolution' => 'activated',
            'reference' => $reference,
            'zoom_meeting_id' => $zoomMeetingId,
        ]);

        $this->info("Meeting {$meeting->id} was activated.");

        return self::SUCCESS;
    }

    private function markFailed(Meeting $meeting, string $reference): int
    {
        $this->resolver->markNotCreated($meeting);

        Log::info('Manual Zoom meeting resolution completed', [
            'meeting_id' => $meeting->id,
            'resolution' => 'marked_failed',
            'reference' => $reference,
        ]);

        $this->info("Meeting {$meeting->id} was marked failed.");

        return self::SUCCESS;
    }
}
