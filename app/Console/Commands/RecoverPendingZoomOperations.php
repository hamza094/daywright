<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Meetings\RecoverPendingZoomMeetingOperations;
use App\Console\Commands\Concerns\ValidatesLimitOption;
use Illuminate\Console\Command;
use Throwable;

final class RecoverPendingZoomOperations extends Command
{
    use ValidatesLimitOption;

    private const int MAX_LIMIT = 100;

    protected $signature = 'meetings:recover-pending {--limit=25}';

    protected $description = 'Recover pending Zoom meeting operations (update and delete)';

    public function __construct(
        private readonly RecoverPendingZoomMeetingOperations $recoveryAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->parseLimit(self::MAX_LIMIT);

        if ($limit === null) {
            return 1;
        }

        try {
            $processed = 0;

            for ($i = 0; $i < $limit; $i++) {
                if (! $this->recoveryAction->execute()) {
                    break;
                }

                $processed++;
            }

            $this->info("Processed {$processed} pending operations.");

            return 0;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Pending operation recovery failed. Check the application logs.');

            return 1;
        }
    }
}
