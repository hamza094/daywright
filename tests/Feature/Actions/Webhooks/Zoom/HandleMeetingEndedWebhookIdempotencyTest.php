<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingEndedWebhook;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use App\Enums\WebhookInboxState;
use App\Jobs\SendMeetingEndedNotification;
use App\Models\Meeting;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class HandleMeetingEndedWebhookIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_already_ended_meeting_handled_gracefully(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 456,
            'status' => 'ended',
        ]);

        $dto = new MeetingEndedWebhookData(
            meetingId: 456,
            startTime: '2024-06-24T11:49:48Z',
            endTime: '2024-06-24T12:19:48Z',
            requestId: null,
        );

        $action = app(HandleMeetingEndedWebhook::class);

        // Should handle already-ended meeting gracefully
        $action->handle($dto);
        $meeting->refresh();
        $this->assertEquals('ended', $meeting->status);
    }

    public function test_inbox_processing_handles_repeated_execution(): void
    {
        Bus::fake();

        $meeting = Meeting::factory()->create([
            'meeting_id' => 789,
            'status' => 'started',
        ]);

        $inbox = WebhookInbox::factory()
            ->forEventType('meeting.ended')
            ->create([
                'event_key' => 'test-fingerprint-4',
                'state' => WebhookInboxState::Received,
                'payload' => [
                    'meetingId' => 789,
                    'startTime' => '2024-06-24T11:49:48Z',
                    'endTime' => '2024-06-24T12:19:48Z',
                    'requestId' => null,
                ],
            ]);

        $service = app(ZoomWebhookInboxService::class);

        // First processing
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertEquals('ended', $meeting->status);
        $this->assertNotNull($meeting->ended_notification_pending_at);

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
        $this->assertEquals('ended', $meeting->status); // Still ended, no error
        Bus::assertDispatched(SendMeetingEndedNotification::class, 1);
    }
}
