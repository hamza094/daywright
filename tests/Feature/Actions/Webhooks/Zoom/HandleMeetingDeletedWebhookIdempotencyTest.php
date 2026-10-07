<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Enums\WebhookInboxState;
use App\Models\Meeting;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Safe\json_encode;

final class HandleMeetingDeletedWebhookIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_duplicate_deletes_are_safe(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 123,
            'status' => 'started',
            'sync_status' => MeetingSyncStatus::Active,
        ]);
        $data = new MeetingDeletedWebhookData(123);
        $handler = app(HandleMeetingDeletedWebhook::class);

        $handler->handle($data, (int) now()->addSecond()->valueOf());
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertSame('started', $meeting->status);

        $handler->handle($data, (int) now()->addSecond()->valueOf());
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
    }

    public function test_matching_delete_webhook_finalizes_pending_delete_and_clears_operation_state(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 124,
            'sync_status' => MeetingSyncStatus::Deleting,
            'sync_operation_type' => MeetingSyncOperationType::Delete,
            'sync_operation_id' => 'delete-operation',
            'sync_claim_token' => 'recovery-claim',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_available_at' => now()->addMinute(),
        ]);

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(124),
            (int) now()->addSecond()->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_operation_type);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_lease_expires_at);
        $this->assertNull($meeting->sync_available_at);
    }

    public function test_delete_webhook_overrides_pending_update_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 125,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'pending-update-operation',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'update-claim',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(125),
            (int) now()->addSecond()->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_operation_type);
        $this->assertNull($meeting->sync_payload);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_lease_expires_at);
    }

    public function test_stale_delete_webhook_cannot_override_pending_update(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 126,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'pending-update-operation',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_started_at' => now(),
            'last_zoom_event_timestamp' => (int) now()->valueOf(),
        ]);

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(126),
            (int) now()->subSecond()->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertSame('pending-update-operation', $meeting->sync_operation_id);
        $this->assertNotNull($meeting->sync_payload);
    }

    public function test_delete_webhook_can_complete_a_failed_delete_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 128,
            'sync_status' => MeetingSyncStatus::DeleteFailed,
            'sync_operation_type' => MeetingSyncOperationType::Delete,
            'sync_operation_id' => 'failed-delete-operation',
        ]);

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(128),
            (int) now()->addSecond()->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
    }

    public function test_delete_webhook_overrides_failed_update_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 129,
            'sync_status' => MeetingSyncStatus::UpdateFailed,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'failed-update-operation',
            'sync_payload' => json_encode(['topic' => 'Failed Topic']),
        ]);

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(129),
            (int) now()->addSecond()->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_payload);
    }

    public function test_stale_delete_webhook_cannot_overwrite_a_completed_newer_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 130,
            'sync_status' => MeetingSyncStatus::Active,
            'sync_started_at' => now(),
            'synced_at' => now(),
            'last_zoom_event_timestamp' => (int) now()->valueOf(),
        ]);

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(130),
            (int) now()->subSecond()->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
    }

    public function test_missing_meeting_is_handled_gracefully(): void
    {
        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(999),
            (int) now()->addSecond()->valueOf(),
        );

        $this->assertDatabaseMissing('meetings', ['meeting_id' => 999]);
    }

    public function test_delete_webhook_without_timestamp_is_processed(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 131,
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        app(HandleMeetingDeletedWebhook::class)->handle(new MeetingDeletedWebhookData(131));

        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->refresh()->sync_status);
    }

    public function test_inbox_processing_handles_repeated_execution(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 456,
            'status' => 'started',
            'sync_status' => MeetingSyncStatus::Active,
        ]);
        $inbox = WebhookInbox::factory()->forEventType('meeting.deleted')->create([
            'event_key' => 'test-fingerprint-6',
            'provider_occurred_at' => (int) now()->addSecond()->valueOf(),
            'state' => WebhookInboxState::Received,
            'payload' => ['meetingId' => 456, 'requestId' => null],
        ]);

        $service = app(ZoomWebhookInboxService::class);
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);

        $inbox->update([
            'state' => WebhookInboxState::Received,
            'claim_token' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'attempts' => 2,
        ]);

        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
    }

    public function test_late_delete_callback_after_newer_operation_is_rejected(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 132,
            'sync_status' => MeetingSyncStatus::Deleting,
            'sync_operation_type' => MeetingSyncOperationType::Delete,
            'sync_operation_id' => 'old-delete-operation',
        ]);

        $handler = app(HandleMeetingDeletedWebhook::class);

        // Simulate newer operation completing with a newer timestamp
        $newerTimestamp = (int) now()->addSecond()->valueOf();
        $meeting->update([
            'sync_status' => MeetingSyncStatus::Active,
            'sync_operation_id' => 'new-operation',
            'sync_operation_type' => null,
            'synced_at' => now(),
            'last_zoom_event_timestamp' => $newerTimestamp,
        ]);

        // Now invoke the old delete callback with a stale timestamp
        $handler->handle(
            new MeetingDeletedWebhookData(132),
            (int) now()->subSecond()->valueOf(),
        );

        $meeting->refresh();
        // The old delete callback should be rejected due to stale timestamp
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame($newerTimestamp, $meeting->last_zoom_event_timestamp);
    }

    public function test_delete_with_equal_timestamp_wins_over_an_update(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 133,
            'sync_status' => MeetingSyncStatus::Active,
        ]);
        $timestamp = (int) now()->subSeconds(10)->valueOf();
        $handler = app(\App\Actions\Webhooks\Zoom\HandleMeetingUpdatedWebhook::class);

        $handler->handle(new \App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData(133, ['topic' => 'Updated'], null), $timestamp);
        app(HandleMeetingDeletedWebhook::class)->handle(new MeetingDeletedWebhookData(133), $timestamp);

        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->refresh()->sync_status);
    }

    /** @param array<string, mixed> $attributes */
    private function createMeeting(array $attributes): Meeting
    {
        $meeting = Meeting::factory()->create($attributes);
        $this->assertInstanceOf(Meeting::class, $meeting);

        return $meeting;
    }
}
