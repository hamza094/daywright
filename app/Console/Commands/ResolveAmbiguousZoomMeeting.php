<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Meetings\ResolveAmbiguousZoomMeetingManually;
use App\Enums\Meeting\MeetingRecoveryOutcome;
use App\Models\Meeting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Safe\preg_match;

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
        $validated = $this->validateAndPrepareInput();

        if ($validated === null) {
            return self::FAILURE;
        }

        if ($validated['cancelled']) {
            return self::SUCCESS;
        }

        return $this->executeResolution($validated);
    }

    /**
     * @return array{meeting: Meeting, meeting_id: int, zoom_meeting_id: string|null, reference: string, cancelled: bool}|null
     */
    private function validateAndPrepareInput(): ?array
    {
        $meetingIdArgument = $this->argument('meeting_id');
        $zoomMeetingId = $this->option('zoom-meeting-id');
        $markFailed = (bool) $this->option('mark-failed');
        $reference = $this->option('reference');
        $hasZoomMeetingId = is_string($zoomMeetingId) && $zoomMeetingId !== '';

        // Validate meeting_id is a positive integer string without leading zeroes or overflow
        if (! preg_match('/^[1-9]\d*$/', $meetingIdArgument)
            || filter_var($meetingIdArgument, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]) === false
        ) {
            $this->error('The meeting_id must be a positive integer.');

            return null;
        }

        $meetingId = (int) $meetingIdArgument;

        if (! is_string($reference) || $reference === '') {
            $this->error('A ticket or incident reference is required.');

            return null;
        }

        if ($hasZoomMeetingId === $markFailed) {
            $this->error('Provide exactly one of --zoom-meeting-id or --mark-failed.');

            return null;
        }

        // Validate zoom-meeting-id is a positive integer string if provided
        if ($hasZoomMeetingId && ! preg_match('/^[1-9]\d*$/', $zoomMeetingId)) {
            $this->error('The zoom-meeting-id must be a positive integer string.');

            return null;
        }

        $meeting = Meeting::find($meetingId);

        if (! $meeting instanceof Meeting) {
            $this->error("Meeting not found: {$meetingId}");

            return null;
        }

        if (! $meeting->awaitsManualZoomRecovery()) {
            $this->error('Meeting is not awaiting manual recovery.');

            return null;
        }

        if (! (bool) $this->option('force') && ! $this->confirm("Resolve meeting {$meetingId} with reference {$reference}?")) {
            $this->info('Operation cancelled.');

            return [
                'meeting' => $meeting,
                'meeting_id' => $meetingId,
                'zoom_meeting_id' => $hasZoomMeetingId ? $zoomMeetingId : null,
                'reference' => $reference,
                'cancelled' => true,
            ];
        }

        return [
            'meeting' => $meeting,
            'meeting_id' => $meetingId,
            'zoom_meeting_id' => $hasZoomMeetingId ? $zoomMeetingId : null,
            'reference' => $reference,
            'cancelled' => false,
        ];
    }

    /**
     * @param  array{meeting: Meeting, meeting_id: int, zoom_meeting_id: string|null, reference: string, cancelled: bool}  $validated
     */
    private function executeResolution(array $validated): int
    {
        $meeting = $validated['meeting'];
        $meetingId = $validated['meeting_id'];
        $zoomMeetingId = $validated['zoom_meeting_id'];
        $reference = $validated['reference'];

        try {
            if ($zoomMeetingId !== null) {
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
