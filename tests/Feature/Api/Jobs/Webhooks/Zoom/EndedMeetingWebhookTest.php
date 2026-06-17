<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingEndedWebhook;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Events\MeetingStatusUpdate;
use App\Jobs\SendMeetingEndedNotification;
use App\Jobs\Webhooks\Zoom\MeetingEndedWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

class EndedMeetingWebhookTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function notifies_project_members_on_meeting_ended(): void
    {
        Bus::fake();
        Event::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
        ]);

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_ended.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $fixture['payload']['object'];
        $meetingId = (string) $object['id'];
        $startTime = $object['start_time'] ?? null;
        $endTime = $object['end_time'] ?? null;

        $job = new MeetingEndedWebhook(new MeetingEndedWebhookData(
            meetingId: $meetingId,
            startTime: $startTime,
            endTime: $endTime,
            requestId: null,
        ));

        $job->handle(app(HandleMeetingEndedWebhook::class));

        $this->assertEquals('ended', $meeting->fresh()->status);

        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);

        Bus::assertDispatched(SendMeetingEndedNotification::class, fn (SendMeetingEndedNotification $job): bool => $job->meetingId === $meeting->id);
    }

    /** @test */
    public function allows_missing_optional_timestamps_without_failing(): void
    {
        Bus::fake();
        Event::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
        ]);

        $job = new MeetingEndedWebhook(new MeetingEndedWebhookData(
            meetingId: 813,
            startTime: null,
            endTime: null,
            requestId: null,
        ));

        $job->handle(app(HandleMeetingEndedWebhook::class));

        $this->assertEquals('ended', $meeting->fresh()->status);
        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);
        Bus::assertDispatched(SendMeetingEndedNotification::class);
    }

    /** @test */
    public function ignores_missing_meeting_without_failing(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $job = new MeetingEndedWebhook(new MeetingEndedWebhookData(
            meetingId: 999999,
            startTime: null,
            endTime: null,
            requestId: null,
        ));

        $job->handle(app(HandleMeetingEndedWebhook::class));

        Bus::assertNotDispatched(SendMeetingEndedNotification::class);
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function does_not_send_duplicate_notifications_when_meeting_is_already_ended(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
            'ended_notification_sent_at' => now(),
        ]);

        $job = new MeetingEndedWebhook(new MeetingEndedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            endTime: '2024-06-24T12:00:00Z',
            requestId: null,
        ));

        $job->handle(app(HandleMeetingEndedWebhook::class));

        $this->assertEquals('ended', $meeting->fresh()->status);
        Bus::assertNotDispatched(SendMeetingEndedNotification::class);
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function ignores_webhook_if_sync_status_is_not_active(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
            'sync_status' => MeetingSyncStatus::Deleting->value,
        ]);

        $job = new MeetingEndedWebhook(new MeetingEndedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            endTime: '2024-06-24T12:00:00Z',
            requestId: 'zoom-ended-inactive',
        ));

        $job->handle(app(HandleMeetingEndedWebhook::class));

        $this->assertEquals('waiting', $meeting->fresh()->status);
        Bus::assertNotDispatched(SendMeetingEndedNotification::class);
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function sends_notification_on_retry_when_meeting_already_ended_but_notification_not_sent(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
            'ended_notification_sent_at' => null,
        ]);

        $job = new MeetingEndedWebhook(new MeetingEndedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            endTime: '2024-06-24T12:00:00Z',
            requestId: 'zoom-ended-retry',
        ));

        $job->handle(app(HandleMeetingEndedWebhook::class));

        // Job should be dispatched on retry
        Bus::assertDispatched(SendMeetingEndedNotification::class);
    }
}
