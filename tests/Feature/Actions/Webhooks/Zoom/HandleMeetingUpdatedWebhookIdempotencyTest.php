<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingUpdatedWebhook;
use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Enums\WebhookInboxState;
use App\Models\Meeting;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;

use function Safe\json_encode;

final class HandleMeetingUpdatedWebhookIdempotencyTest extends TestCase
{
    use InteractsWithZoom, LazilyRefreshDatabase;

    public function test_noop_updates_are_ignored(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 123,
            'topic' => 'Original Topic',
            'start_time' => '2024-06-24T11:49:48Z',
            'duration' => 30,
        ]);

        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 123,
            changes: [
                'topic' => 'Original Topic',
                'start_time' => '2024-06-24T11:49:48Z',
                'duration' => 30,
            ],
            requestId: null,
        ), (int) now()->addSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame('Original Topic', $meeting->topic);
        $this->assertEquals('2024-06-24 11:49:48', $meeting->start_time);
        $this->assertSame(30, $meeting->duration);
    }

    public function test_matching_update_webhook_finalizes_the_current_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 654,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'current-operation',
            'sync_payload' => json_encode(['topic' => 'Requested Topic', 'duration' => 45]),
            'sync_claim_token' => 'active-claim',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_available_at' => now()->addMinute(),
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(654, 'Requested Topic', 45, 'New agenda'));

        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 654,
            changes: ['topic' => 'Requested Topic', 'duration' => 45, 'agenda' => 'New agenda'],
            requestId: 'webhook-request',
        ), (int) now()->addSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame('Requested Topic', $meeting->topic);
        $this->assertSame(45, $meeting->duration);
        $this->assertSame('New agenda', $meeting->agenda);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_operation_type);
        $this->assertNull($meeting->sync_payload);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_lease_expires_at);
        $this->assertNull($meeting->sync_available_at);
    }

    public function test_mismatching_or_partial_update_webhook_cannot_overwrite_pending_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 655,
            'topic' => 'Original Topic',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'current-operation',
            'sync_payload' => json_encode(['topic' => 'Requested Topic', 'duration' => 45]),
        ]);

        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 655,
            changes: ['topic' => 'Stale Topic'],
            requestId: 'stale-request',
        ), (int) now()->addSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertSame('Original Topic', $meeting->topic);
        $this->assertSame('current-operation', $meeting->sync_operation_id);
        $this->assertNotNull($meeting->sync_payload);
    }

    public function test_matching_late_update_webhook_finalizes_update_failed_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 656,
            'sync_status' => MeetingSyncStatus::UpdateFailed,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'failed-operation',
            'sync_payload' => json_encode(['topic' => 'Eventually Updated']),
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(656, 'Eventually Updated'));

        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 656,
            changes: ['topic' => 'Eventually Updated'],
            requestId: 'late-request',
        ), (int) now()->addSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame('Eventually Updated', $meeting->topic);
        $this->assertNull($meeting->sync_operation_id);
    }

    public function test_old_matching_webhook_cannot_complete_a_new_pending_operation_or_restore_other_fields(): void
    {
        $operationStartedAt = now();
        $meeting = $this->createMeeting([
            'meeting_id' => 659,
            'topic' => 'Current Topic',
            'agenda' => 'Current agenda',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'new-operation',
            'sync_payload' => json_encode(['topic' => 'Repeated Topic']),
            'sync_started_at' => $operationStartedAt,
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(659, 'Repeated Topic', 30, 'Current agenda'));
        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 659,
            changes: ['topic' => 'Repeated Topic', 'agenda' => 'Stale agenda'],
            requestId: 'older-matching-event',
        ), (int) $operationStartedAt->copy()->subSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertSame('new-operation', $meeting->sync_operation_id);
        $this->assertSame('Current agenda', $meeting->agenda);
    }

    public function test_timestamp_tie_does_not_complete_pending_update_without_correlation(): void
    {
        $operationStartedAt = now();
        $meeting = $this->createMeeting([
            'meeting_id' => 660,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'tied-operation',
            'sync_payload' => json_encode(['topic' => 'Requested Topic']),
            'sync_started_at' => $operationStartedAt,
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(660, 'Requested Topic'));
        $persistedOperationStart = $meeting->fresh()->sync_started_at;

        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 660,
            changes: ['topic' => 'Requested Topic'],
            requestId: 'timestamp-tie',
        ), (int) $persistedOperationStart->valueOf());

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertSame('tied-operation', $meeting->sync_operation_id);
    }

    public function test_stale_update_webhook_cannot_overwrite_a_completed_newer_operation(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 657,
            'topic' => 'Newer Topic',
            'sync_status' => MeetingSyncStatus::Active,
            'sync_reconcile_before_at' => now(),
            'synced_at' => now(),
            'last_zoom_event_timestamp' => null,
        ]);
        $this->fakeZoom()->findsMeeting(new ZoomMeeting(
            meeting_id: 657,
            topic: 'Newer Topic',
            agenda: '',
            created_at: '2026-01-01 00:00:00',
            duration: 30,
            start_time: '2026-01-01 00:00:00',
            start_url: 'https://zoom.us/s/657',
            join_url: 'https://zoom.us/j/657',
            status: 'waiting',
            timezone: 'UTC',
            password: '',
            join_before_host: false,
        ));

        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 657,
            changes: ['topic' => 'Older Topic'],
            requestId: 'old-event',
        ), (int) now()->subSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame('Newer Topic', $meeting->topic);
    }

    public function test_same_second_delayed_webhook_reconciles_with_millisecond_completion_cutoff(): void
    {
        $completedAt = Carbon::parse('2026-10-07 12:00:05.900');
        $earlierEventAt = Carbon::parse('2026-10-07 12:00:05.400');
        Carbon::setTestNow($completedAt);

        try {
            $meeting = $this->createMeeting([
                'meeting_id' => 663,
                'topic' => 'Update B',
                'sync_status' => MeetingSyncStatus::Active,
                'sync_reconcile_before_at' => now(),
                'synced_at' => now(),
                'last_zoom_event_timestamp' => null,
            ]);
            $storedCutoff = $meeting->fresh()->sync_reconcile_before_at;

            $this->assertSame($completedAt->valueOf(), $storedCutoff->valueOf());
            $this->fakeZoom()->findsMeeting($this->zoomMeeting(663, 'Update B'));

            app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
                meetingId: 663,
                changes: ['topic' => 'Delayed Update A'],
                requestId: 'same-second-delayed-update',
            ), (int) $earlierEventAt->valueOf());

            $this->assertSame('Update B', $meeting->refresh()->topic);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_precision_upgrade_preserves_same_second_reconciliation_cutoff(): void
    {
        Schema::table('meetings', function ($table): void {
            $table->timestamp('sync_reconcile_before_at')->nullable()->change();
        });

        $migration = require database_path('migrations/2026_10_07_000002_change_sync_reconcile_before_at_precision_on_meetings_table.php');
        $migration->up();

        $completedAt = Carbon::parse('2026-10-07 12:00:05.900');
        $earlierEventAt = Carbon::parse('2026-10-07 12:00:05.400');
        Carbon::setTestNow($completedAt);

        try {
            $meeting = $this->createMeeting([
                'meeting_id' => 665,
                'topic' => 'Update B',
                'sync_status' => MeetingSyncStatus::Active,
                'sync_reconcile_before_at' => now(),
                'synced_at' => now(),
                'last_zoom_event_timestamp' => null,
            ]);

            $this->assertSame($completedAt->valueOf(), $meeting->fresh()->sync_reconcile_before_at->valueOf());
            $this->fakeZoom()->findsMeeting($this->zoomMeeting(665, 'Update B'));

            app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
                meetingId: 665,
                changes: ['topic' => 'Delayed Update A'],
                requestId: 'upgraded-same-second-delayed-update',
            ), (int) $earlierEventAt->valueOf());

            $this->assertSame('Update B', $meeting->refresh()->topic);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pending_update_is_marked_deleted_when_reconciliation_confirms_zoom_meeting_missing(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 664,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'pending-operation',
            'sync_payload' => json_encode(['topic' => 'Requested Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_available_at' => now()->addMinute(),
            'sync_reconcile_before_at' => now(),
        ]);
        $this->fakeZoom()->meetingNotFound();

        app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
            meetingId: 664,
            changes: ['agenda' => 'Partial unrelated callback'],
            requestId: 'partial-callback-for-missing-meeting',
        ), (int) now()->subSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_operation_type);
        $this->assertNull($meeting->sync_payload);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_lease_expires_at);
        $this->assertNull($meeting->sync_available_at);
    }

    public function test_out_of_order_update_events_cannot_overwrite_newer_event_state(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 658,
            'topic' => 'Original Topic',
        ]);
        $handler = app(HandleMeetingUpdatedWebhook::class);
        $newerTimestamp = (int) now()->addSeconds(2)->valueOf();

        $handler->handle(new MeetingUpdatedWebhookData(658, ['topic' => 'Newer Topic'], null), $newerTimestamp);
        $handler->handle(new MeetingUpdatedWebhookData(658, ['topic' => 'Older Topic'], null), $newerTimestamp - 1);

        $meeting->refresh();
        $this->assertSame('Newer Topic', $meeting->topic);
        $this->assertSame($newerTimestamp, $meeting->last_zoom_event_timestamp);
    }

    public function test_deleting_and_deleted_meetings_ignore_updates(): void
    {
        foreach ([MeetingSyncStatus::Deleting, MeetingSyncStatus::Deleted] as $status) {
            $meeting = $this->createMeeting([
                'meeting_id' => random_int(1000, 9999),
                'status' => $status === MeetingSyncStatus::Deleted ? 'deleted' : 'deleting',
                'sync_status' => $status,
                'topic' => 'Original Topic',
            ]);

            app(HandleMeetingUpdatedWebhook::class)->handle(new MeetingUpdatedWebhookData(
                meetingId: $meeting->meeting_id,
                changes: ['topic' => 'Updated Topic'],
                requestId: null,
            ), (int) now()->addSecond()->valueOf());

            $meeting->refresh();
            $this->assertSame('Original Topic', $meeting->topic);
        }
    }

    public function test_inbox_processing_handles_repeated_execution(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 321,
            'topic' => 'Original Topic',
            'start_time' => '2024-06-24T11:49:48Z',
            'duration' => 30,
        ]);

        $inbox = WebhookInbox::factory()->forEventType('meeting.updated')->create([
            'event_key' => 'test-fingerprint-5',
            'provider_occurred_at' => (int) now()->addSecond()->valueOf(),
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
        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertSame('Updated Topic', $meeting->topic);

        $inbox->update([
            'state' => WebhookInboxState::Received,
            'claim_token' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'attempts' => 2,
        ]);

        $service->process($inbox->id);
        $meeting->refresh();
        $this->assertSame('Updated Topic', $meeting->topic);
    }

    public function test_late_callback_after_newer_operation_is_rejected(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 659,
            'topic' => 'Original Topic',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'old-operation',
            'sync_payload' => json_encode(['topic' => 'Old Topic']),
        ]);

        $handler = app(HandleMeetingUpdatedWebhook::class);

        // Simulate newer operation completing with a newer timestamp
        $newerTimestamp = (int) now()->addSecond()->valueOf();
        $meeting->update([
            'sync_status' => MeetingSyncStatus::Active,
            'sync_operation_id' => 'new-operation',
            'sync_operation_type' => null,
            'sync_payload' => null,
            'synced_at' => now(),
            'last_zoom_event_timestamp' => $newerTimestamp,
        ]);

        // Now invoke the old callback with a stale timestamp
        $handler->handle(new MeetingUpdatedWebhookData(
            meetingId: 659,
            changes: ['topic' => 'Old Topic'],
            requestId: 'old-request',
        ), (int) now()->subSecond()->valueOf());

        $meeting->refresh();
        // The old callback should be rejected due to stale timestamp
        $this->assertSame('Original Topic', $meeting->topic);
        $this->assertSame($newerTimestamp, $meeting->last_zoom_event_timestamp);
    }

    public function test_additional_allowlisted_fields_from_webhook_are_preserved(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 660,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'current-operation',
            'sync_payload' => json_encode(['topic' => 'Requested Topic']),
            'join_url' => 'https://zoom.us/j/old',
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(660, 'Requested Topic', agenda: 'New agenda', joinUrl: 'https://zoom.us/j/new'));

        // Webhook includes agenda (allowlisted) that wasn't in the original request
        $handler = app(HandleMeetingUpdatedWebhook::class);
        $handler->handle(new MeetingUpdatedWebhookData(
            meetingId: 660,
            changes: [
                'topic' => 'Requested Topic',
                'agenda' => 'New agenda',
                'join_url' => 'https://zoom.us/j/new',
            ],
            requestId: 'webhook-request',
        ), (int) now()->addSecond()->valueOf());

        $meeting->refresh();
        $this->assertSame('Requested Topic', $meeting->topic);
        $this->assertSame('New agenda', $meeting->agenda);
        $this->assertSame('https://zoom.us/j/new', $meeting->join_url);
        $this->assertNull($meeting->sync_payload);
    }

    public function test_delayed_update_then_delete_is_not_rejected_by_processing_time(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 661,
            'topic' => 'Original Topic',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'pending-update',
            'sync_payload' => json_encode(['topic' => 'Updated Topic']),
            'sync_started_at' => now()->subSeconds(20),
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(661, 'Updated Topic'));
        $updateTimestamp = (int) now()->subSeconds(10)->valueOf();
        $deleteTimestamp = $updateTimestamp + 1000;

        app(HandleMeetingUpdatedWebhook::class)->handle(
            new MeetingUpdatedWebhookData(661, ['topic' => 'Updated Topic'], null),
            $updateTimestamp,
        );
        app(\App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook::class)->handle(
            new \App\DataTransferObjects\Zoom\MeetingDeletedWebhookData(661),
            $deleteTimestamp,
        );

        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->refresh()->sync_status);
    }

    public function test_delete_wins_when_update_and_delete_have_the_same_timestamp(): void
    {
        $meeting = $this->createMeeting([
            'meeting_id' => 662,
            'topic' => 'Original Topic',
            'sync_status' => MeetingSyncStatus::Active,
        ]);
        $timestamp = (int) now()->subSeconds(10)->valueOf();

        app(HandleMeetingUpdatedWebhook::class)->handle(
            new MeetingUpdatedWebhookData(662, ['topic' => 'Updated Topic'], null),
            $timestamp,
        );
        app(\App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook::class)->handle(
            new \App\DataTransferObjects\Zoom\MeetingDeletedWebhookData(662),
            $timestamp,
        );

        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->refresh()->sync_status);
    }

    /** @param array<string, mixed> $attributes */
    private function createMeeting(array $attributes): Meeting
    {
        $meeting = Meeting::factory()->create($attributes);
        $this->assertInstanceOf(Meeting::class, $meeting);

        return $meeting;
    }

    private function zoomMeeting(int $id, string $topic, int $duration = 30, string $agenda = '', string $joinUrl = ''): ZoomMeeting
    {
        return new ZoomMeeting(
            meeting_id: $id,
            topic: $topic,
            agenda: $agenda,
            created_at: '2026-01-01 00:00:00',
            duration: $duration,
            start_time: '2026-01-01 00:00:00',
            start_url: "https://zoom.us/s/{$id}",
            join_url: $joinUrl !== '' ? $joinUrl : "https://zoom.us/j/{$id}",
            status: 'waiting',
            timezone: 'UTC',
            password: '',
            join_before_host: false,
        );
    }
}
