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
use Illuminate\Queue\Middleware\RateLimited;
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

    public function __construct(
        public int $meetingId,
        public int $userId
    ) {
        $this->onQueue('default');
    }

    public function tags(): array
    {
        return ['cancel-zoom-meetings', 'meeting:'.$this->meetingId];
    }

    public function middleware(): array
    {
        return [
            new RateLimited('zoom-api'),
        ];
    }

    public function handle(Zoom $zoomService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        try {
            $zoomService->deleteMeeting($this->meetingId, $user);
        } catch (NotFoundException) {
            return;
        } catch (ZoomException $exception) {
            throw $exception;
        }
    }


    public function failed(Throwable $exception): void
    {
        Log::channel(self::LOG_CHANNEL)->error('CancelZoomMeetingsJob failed permanently.', [
            'meeting_id' => $this->meetingId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
