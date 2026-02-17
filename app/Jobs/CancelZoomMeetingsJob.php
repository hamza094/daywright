<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Interfaces\Zoom;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelZoomMeetingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const LOG_CHANNEL = 'zoom';

    public int $tries = 3;

    /**
     * @param  array<int, array{meeting_id:int, user_id:int}>  $meetings
     */
    public function __construct(public array $meetings) {}

    public function handle(Zoom $zoomService): void
    {
        foreach ($this->meetings as $meeting) {
            $user = User::query()->find($meeting['user_id']);

            if (! $user) {
                continue;
            }

            try {
                $zoomService->deleteMeeting($meeting['meeting_id'], $user);
            } catch (NotFoundException) {
                continue;
            } catch (ZoomException $exception) {
                if ($exception->getCode() === 429) {
                    $this->release(60);

                    return;
                }

                Log::channel(self::LOG_CHANNEL)->error('Failed to cancel Zoom meeting.', [
                    'meeting_id' => $meeting['meeting_id'],
                    'user_id' => $meeting['user_id'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel(self::LOG_CHANNEL)->error('CancelZoomMeetingsJob failed permanently.', [
            'meeting_ids' => array_column($this->meetings, 'meeting_id'),
            'error' => $exception->getMessage(),
        ]);
    }
}
