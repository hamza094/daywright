<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks\Zoom;

use App\Models\Meeting;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteMeetingWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int|string
     */
    public $meeting_id;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->meeting_id = $data['meeting_id'];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(key: "zoom-meeting:{$this->meeting_id}", releaseAfter: 5))
                ->shared()
                ->expireAfter(120),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $meeting = Meeting::where('meeting_id', $this->meeting_id)->firstOrFail();

            $meeting->delete();

            Log::channel('webhook')->info('Meeting deleted successfully', ['meeting_id' => $this->meeting_id]);
        } catch (ModelNotFoundException $e) {
            Log::channel('webhook')->info('Meeting not available in database', ['meeting_id' => $this->meeting_id]);

            return;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::channel('webhook')->error('Delete Meeting webhook job failed', [
            'meeting_id' => $this->meeting_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30];
    }
}
