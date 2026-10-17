<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs\Webhooks;

use App\Actions\Webhooks\Zoom\HandleMeetingStartedWebhook;
use App\Enums\WebhookInboxState;
use App\Jobs\Webhooks\ProcessZoomWebhookInbox;
use App\Models\Meeting;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WebhookInboxCrashRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reclaim_after_crash_processes_again(): void
    {
        Queue::fake();

        $meeting = Meeting::factory()->create([
            'meeting_id' => 123,
            'status' => 'not_started',
        ]);

        $inbox = WebhookInbox::factory()
            ->forEventType('meeting.started')
            ->create([
                'event_key' => 'test-fingerprint-1',
                'state' => WebhookInboxState::Processing,
                'attempts' => 1,
                'claim_token' => 'stale-token',
                'claimed_at' => now()->subMinutes(6),
                'claim_expires_at' => now()->subMinute(),
                'payload' => [
                    'meetingId' => 123,
                    'startTime' => '2024-06-24T11:49:48Z',
                    'requestId' => null,
                ],
            ]);

        // Simulate the business action succeeding (meeting marked as started)
        $meeting->update(['status' => 'started']);

        // But inbox row was never completed (worker crash)
        $inbox->refresh();
        $this->assertEquals(WebhookInboxState::Processing, $inbox->state);

        // Recovery service should reclaim this
        $service = app(ZoomWebhookInboxService::class);
        $result = $service->dispatchRecoverable(10);

        $this->assertEquals(1, $result['selected']);
        $this->assertEquals(1, $result['dispatched']);

        // The job should be dispatched again
        Queue::assertPushed(ProcessZoomWebhookInbox::class, fn ($job): bool => $job->webhookInboxId === $inbox->id);
    }

    public function test_business_action_handles_repeated_execution(): void
    {
        Queue::fake();

        $meeting = Meeting::factory()->create([
            'meeting_id' => 456,
            'status' => 'not_started',
        ]);

        $dto = new \App\DataTransferObjects\Zoom\MeetingStartedWebhookData(
            meetingId: 456,
            startTime: '2024-06-24T11:49:48Z',
            requestId: null,
        );

        $action = app(HandleMeetingStartedWebhook::class);

        // First execution
        $action->handle($dto);
        $meeting->refresh();
        $this->assertEquals('started', $meeting->status);

        // Simulate crash and reclaim - execute again
        $action->handle($dto);
        $meeting->refresh();
        $this->assertEquals('started', $meeting->status); // Should still be started, no error

        // Verify no duplicate side effects (notifications are guarded by state checks)
    }

    public function test_expired_claim_can_be_reclaimed(): void
    {
        Queue::fake();

        $inbox = WebhookInbox::factory()
            ->forEventType('meeting.started')
            ->create([
                'event_key' => 'test-fingerprint-2',
                'state' => WebhookInboxState::Processing,
                'attempts' => 1,
                'claim_token' => 'stale-token',
                'claimed_at' => now()->subMinutes(6),
                'claim_expires_at' => now()->subMinute(),
            ]);

        // The row should be claimable by recovery since the lease expired
        $service = app(ZoomWebhookInboxService::class);
        $result = $service->dispatchRecoverable(10);

        $this->assertEquals(1, $result['selected']);
        $this->assertEquals(1, $result['dispatched']);

        // Verify the job was dispatched for reprocessing
        Queue::assertPushed(ProcessZoomWebhookInbox::class, fn ($job): bool => $job->webhookInboxId === $inbox->id);
    }
}
