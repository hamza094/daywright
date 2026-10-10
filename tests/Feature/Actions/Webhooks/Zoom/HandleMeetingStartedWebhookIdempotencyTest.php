<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingStartedWebhook;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\Enums\WebhookInboxState;
use App\Jobs\SendMeetingStartedNotification;
use App\Models\Meeting;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class HandleMeetingStartedWebhookIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ended_meeting_cannot_be_restarted(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 456,
            'status' => 'ended',
        ]);

        $dto = new MeetingStartedWebhookData(
            meetingId: 456,
            startTime: '2024-06-24T11:49:48Z',
            requestId: null,
        );

        $action = app(HandleMeetingStartedWebhook::class);

        // Should not process already-ended meeting
        $action->handle($dto);
        $meeting->refresh();
        $this->assertEquals('ended', $meeting->status);
    }

    public function test_inbox_processing_handles_repeated_execution(): void
    {
        Bus::fake();

        $meeting = Meeting::factory()->create([
            'meeting_id' => 789,
            'status' => 'not_started',
        ]);

        $inbox = WebhookInbox::factory()
            ->forEventType('meeting.started')
            ->create([
                'event_key' => 'test-fingerprint-3',
                'state' => WebhookInboxState::Received,
                'payload' => [
                    'meetingId' => 789,
                    'startTime' => '2024-06-24T11:49:48Z',
                    'requestId' => null,
                ],
            ]);

        $service = app(ZoomWebhookInboxService::class);

        // First processing
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertEquals('started', $meeting->status);
        $this->assertNotNull($meeting->started_notification_pending_at);

        // Simulate reclaim by resetting state
        $inbox->update([
            'state' => WebhookInboxState::Received,
            'claim_token' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'attempts' => 2,
        ]);

        // Second processing (simulating recovery)
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertEquals('started', $meeting->status); // Still started, no error
        Bus::assertDispatched(SendMeetingStartedNotification::class, 1);
    }
}
