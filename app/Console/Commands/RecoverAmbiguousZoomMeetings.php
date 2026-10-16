<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Meetings\RecoverAmbiguousZoomMeeting;
use App\Console\Commands\Concerns\ValidatesLimitOption;
use App\Enums\Meeting\MeetingRecoveryOutcome;
use App\Models\Meeting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecoverAmbiguousZoomMeetings extends Command
{
    use ValidatesLimitOption;

    private const int MAX_LIMIT = 100;

    protected $signature = 'meetings:recover-ambiguous {--limit=25}';

    protected $description = 'Recover meetings in creating or create_unknown state by checking Zoom';

    public function __construct(
        private readonly RecoverAmbiguousZoomMeeting $recoveryAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->parseLimit(self::MAX_LIMIT);

        if ($limit === null) {
            return 1;
        }

        return $this->recoverMeetings($limit);
    }

    private function recoverMeetings(int $limit): int
    {
        try {
            $meetings = Meeting::query()
                ->readyForZoomRecoveryAt(now())
                ->limit($limit)
                ->get();

            $recovered = 0;
            $unresolved = 0;
            $manualReview = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($meetings as $meeting) {
                try {
                    match ($this->recoveryAction->execute($meeting)) {
                        MeetingRecoveryOutcome::Recovered => $recovered++,
                        MeetingRecoveryOutcome::Unresolved => $unresolved++,
                        MeetingRecoveryOutcome::ManualReview => $manualReview++,
                        MeetingRecoveryOutcome::Skipped => $skipped++,
                    };
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('Failed to recover meeting', [
                        'meeting_id' => $meeting->id,
                        'error' => $e::class,
                    ]);
                }
            }

            $this->info("Selected: {$meetings->count()}");
            $this->info("Recovered: {$recovered}");
            $this->info("Unresolved: {$unresolved}");
            $this->info("Manual review: {$manualReview}");
            $this->info("Skipped: {$skipped}");
            $this->info("Failed: {$failed}");

            if ($failed > 0) {
                $this->warn('Some meetings failed to recover');

                return 1;
            }

            $this->info('Ambiguous meeting recovery completed successfully.');

            return 0;
        } catch (Throwable $exception) {
            Log::error('Ambiguous meeting recovery command failed', [
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            $this->error('Ambiguous meeting recovery failed: '.$exception::class);

            return 1;
        }
    }
}
