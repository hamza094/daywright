<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Interfaces\Zoom;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
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
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const string LOG_CHANNEL = 'zoom';

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $meetingId,
        public int $userId
    ) {
        $this->onQueue('default');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 300];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['cancel-zoom-meetings', 'meeting:'.$this->meetingId];
    }

    /**
     * @return array<int, object>
     */
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
        } catch (\Saloon\Exceptions\Request\RequestException $e) {
            if ($e->getResponse()->clientError()) {
                Log::channel(self::LOG_CHANNEL)->warning('Zoom API Client Error during cancellation', [
                    'meeting_id' => $this->meetingId,
                    'status' => $e->getResponse()->status(),
                    'error' => $e->getMessage(),
                ]);

                return; // Don't retry permanent client errors (4xx)
            }
            throw $e; // Re-throw 5xx errors to trigger retry
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
