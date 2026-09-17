<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingUpdatedWebhook;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\WebhookInboxState;
use App\Models\Meeting;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class HandleMeetingUpdatedWebhookIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_noop_updates_are_ignored(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 123,
            'topic' => 'Original Topic',
            'start_time' => '2024-06-24T11:49:48Z',
            'duration' => 30,
        ]);

        $dto = new MeetingUpdatedWebhookData(
            meetingId: 123,
            changes: [
                'topic' => 'Original Topic',
                'start_time' => '2024-06-24T11:49:48Z',
                'duration' => 30,
            ],
            requestId: null,
        );

        $action = app(HandleMeetingUpdatedWebhook::class);

        // Should not update anything (same values)
        $action->handle($dto);
        $meeting->refresh();
        $this->assertEquals('Original Topic', $meeting->topic);
        $this->assertEquals('2024-06-24 11:49:48', $meeting->start_time);
        $this->assertEquals(30, $meeting->duration);
    }

    public function test_deleting_meeting_ignores_updates(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 456,
            'status' => 'deleting',
            'sync_status' => 'deleting',
            'topic' => 'Original Topic',
        ]);

        $dto = new MeetingUpdatedWebhookData(
            meetingId: 456,
            changes: [
                'topic' => 'Updated Topic',
                'start_time' => '2024-06-24T12:00:00Z',
                'duration' => 45,
            ],
            requestId: null,
        );

        $action = app(HandleMeetingUpdatedWebhook::class);

        // Should not update deleting meeting
        $action->handle($dto);
        $meeting->refresh();
        // The topic should remain unchanged since meeting is deleting
        $this->assertEquals('Original Topic', $meeting->topic);
    }

    public function test_deleted_meeting_ignores_updates(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 789,
            'status' => 'deleted',
            'sync_status' => 'deleted',
            'topic' => 'Original Topic',
        ]);

        $dto = new MeetingUpdatedWebhookData(
            meetingId: 789,
            changes: [
                'topic' => 'Updated Topic',
                'start_time' => '2024-06-24T12:00:00Z',
                'duration' => 45,
            ],
            requestId: null,
        );

        $action = app(HandleMeetingUpdatedWebhook::class);

        // Should not update deleted meeting
        $action->handle($dto);
        $meeting->refresh();
        $this->assertEquals('Original Topic', $meeting->topic);
    }

    public function test_inbox_processing_handles_repeated_execution(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 321,
            'topic' => 'Original Topic',
            'start_time' => '2024-06-24T11:49:48Z',
            'duration' => 30,
        ]);

        $inbox = WebhookInbox::factory()
            ->forEventType('meeting.updated')
            ->create([
                'event_key' => 'test-fingerprint-5',
                'state' => WebhookInboxState::Received,
                'payload' => [
                    'meetingId' => 321,
                    'changes' => [
                        'topic' => 'Updated Topic',
                        'start_time' => '2024-06-24T12:00:00Z',
                        'duration' => 45,
                    ],
                    'requestId' => null,
                ],
            ]);

        $service = app(ZoomWebhookInboxService::class);

        // First processing
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertEquals('Updated Topic', $meeting->topic);

        // Simulate reclaim by resetting state
        $inbox->update([
            'state' => WebhookInboxState::Received,
            'claim_token' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'attempts' => 2,
        ]);

        // Second processing (simulating recovery) - should be idempotent
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertEquals('Updated Topic', $meeting->topic); // Still updated
    }
}
