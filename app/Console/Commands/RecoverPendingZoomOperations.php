<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Meetings\RecoverPendingZoomMeetingOperations;
use Illuminate\Console\Command;
use Throwable;

final class RecoverPendingZoomOperations extends Command
{
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
        $limit = min((int) $this->option('limit'), self::MAX_LIMIT);

        if ($limit <= 0) {
            $this->error('Limit must be between 1 and '.self::MAX_LIMIT);

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
