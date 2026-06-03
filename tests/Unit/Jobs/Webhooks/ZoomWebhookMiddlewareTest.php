<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Webhooks;

use App\Jobs\Webhooks\Zoom\DeleteMeetingWebhook;
use App\Jobs\Webhooks\Zoom\MeetingEndsWebhook;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PHPUnit\Framework\TestCase;

class ZoomWebhookMiddlewareTest extends TestCase
{
    /** @test */
    public function zoom_webhook_jobs_use_a_shared_meeting_lock(): void
    {
        $jobs = [
            new StartMeetingWebhook([
                'meeting_id' => 813,
                'start_time' => '2024-06-24T12:00:00Z',
                'request_id' => 'zoom-start-813',
            ]),
            new MeetingEndsWebhook([
                'meeting_id' => 813,
                'start_time' => '2024-06-24T12:00:00Z',
                'end_time' => '2024-06-24T12:30:00Z',
                'request_id' => 'zoom-ended-813',
            ]),
            new UpdateMeetingWebhook([
                'meeting_id' => 813,
                'update_data' => ['topic' => 'Updated topic'],
                'request_id' => 'zoom-update-813',
            ]),
            new DeleteMeetingWebhook([
                'meeting_id' => 813,
                'request_id' => 'zoom-delete-813',
            ]),
        ];

        foreach ($jobs as $job) {
            $middleware = $job->middleware();

            $this->assertCount(1, $middleware);
            $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
            $this->assertTrue($middleware[0]->shareKey);
            $this->assertSame('zoom-meeting:813', $middleware[0]->key);
            $this->assertSame(5, $middleware[0]->releaseAfter);
            $this->assertSame(120, $middleware[0]->expiresAfter);
            $this->assertSame(3, $job->tries);
            $this->assertSame([5, 30], $job->backoff());
            $this->assertSame(120, $job->timeout);
            $this->assertTrue($job->failOnTimeout);
        }
    }

    /** @test */
    public function zoom_webhook_jobs_share_the_same_lock_key_across_job_classes(): void
    {
        $startJob = new StartMeetingWebhook([
            'meeting_id' => 813,
            'start_time' => '2024-06-24T12:00:00Z',
            'request_id' => 'zoom-start-813',
        ]);
        $updateJob = new UpdateMeetingWebhook([
            'meeting_id' => 813,
            'update_data' => ['topic' => 'Updated topic'],
            'request_id' => 'zoom-update-813',
        ]);

        $startLock = $startJob->middleware()[0];
        $updateLock = $updateJob->middleware()[0];

        $this->assertSame($startLock->getLockKey($startJob), $updateLock->getLockKey($updateJob));
    }

    /** @test */
    public function zoom_webhook_jobs_expose_operator_friendly_tags(): void
    {
        $job = new StartMeetingWebhook([
            'meeting_id' => 813,
            'start_time' => '2024-06-24T12:00:00Z',
            'request_id' => 'zoom-start-813',
        ]);

        $this->assertSame([
            'provider:zoom',
            'zoom_operation:zoom.webhook.meeting.started',
            'meeting:813',
            'request:zoom-start-813',
        ], $job->tags());
    }
}
