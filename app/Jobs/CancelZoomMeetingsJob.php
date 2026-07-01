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
use Illuminate\Queue\Middleware\FailOnException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelZoomMeetingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const string LOG_CHANNEL = 'zoom';

    public int $tries = 3;
    public int $timeout = 60;
    public bool $failOnTimeout = true;

    /**
     * @param  array<int, array{meeting_id:int, user_id:int}>  $meetings
     */
    public function __construct(public array $meetings)
    {
        $this->onQueue('default');
    }

    public function tags(): array
    {
        $tags = ['cancel-zoom-meetings'];
        foreach ($this->meetings as $meeting) {
            $tags[] = 'meeting:'.$meeting['meeting_id'];
        }
        return $tags;
    }

    public function middleware(): array
    {
        return [
            new FailOnException(function (Throwable $e) {
                // Fail immediately on authentication/permission errors
                // These are permanent errors that won't be fixed by retrying
                return $e->getCode() === 401 || $e->getCode() === 403;
            }),
        ];
    }

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
