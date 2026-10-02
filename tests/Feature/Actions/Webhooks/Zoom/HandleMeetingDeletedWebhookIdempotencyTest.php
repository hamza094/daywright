<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Enums\WebhookInboxState;
use App\Models\Meeting;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class HandleMeetingDeletedWebhookIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_duplicate_deletes_are_safe(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 123,
            'status' => 'started',
            'sync_status' => 'active',
        ]);

        $dto = new MeetingDeletedWebhookData(
            meetingId: 123,
        );

        $action = app(HandleMeetingDeletedWebhook::class);

        // First deletion
        $action->handle($dto);
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertSame('started', $meeting->status);

        // Second deletion (simulating reclaim after crash)
        $action->handle($dto);
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertSame('started', $meeting->status);
    }

    public function test_missing_meeting_handled_gracefully(): void
    {
        // Create meeting but don't create it in database
        $dto = new MeetingDeletedWebhookData(
            meetingId: 999,
        );

        $action = app(HandleMeetingDeletedWebhook::class);

        // Should handle missing meeting gracefully
        $action->handle($dto);

        // No exception thrown
        $this->assertTrue(true);
    }

    public function test_inbox_processing_handles_repeated_execution(): void
    {
        $meeting = Meeting::factory()->create([
            'meeting_id' => 456,
            'status' => 'started',
            'sync_status' => 'active',
        ]);

        $inbox = WebhookInbox::factory()
            ->forEventType('meeting.deleted')
            ->create([
                'event_key' => 'test-fingerprint-6',
                'state' => WebhookInboxState::Received,
                'payload' => [
                    'meetingId' => 456,
                    'requestId' => null,
                ],
            ]);

        $service = app(ZoomWebhookInboxService::class);

        // First processing
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertSame('started', $meeting->status);

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
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertSame('started', $meeting->status);
    }
}
