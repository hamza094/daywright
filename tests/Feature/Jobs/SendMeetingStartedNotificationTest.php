<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Channels\DatabaseChannel;
use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\Jobs\SendMeetingStartedNotification;
use App\Models\User;
use App\Notifications\Zoom\MeetingStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

class SendMeetingStartedNotificationTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function does_not_send_when_flag_is_already_set(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => now(),
        ]);

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $job->handle();

        Notification::assertNothingSent();
    }

    /** @test */
    public function claims_flag_and_sends_notification(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => null,
            'started_notification_pending_at' => now(),
        ]);

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $job->handle();

        Notification::assertSentTo($assignee, MeetingStarted::class);
        $meeting = $meeting->fresh();
        $this->assertNotNull($meeting->started_notification_sent_at);
        $this->assertNull($meeting->started_notification_pending_at);
    }

    /** @test */
    public function does_not_claim_if_already_claimed_by_another_worker(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => null,
        ]);

        // Simulate another worker claiming the flag
        $meeting->update(['started_notification_sent_at' => now()]);

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $job->handle();

        Notification::assertNothingSent();
    }

    /** @test */
    public function rolls_back_flag_on_notification_send_failure(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => null,
        ]);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Notification service failed'));

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $exceptionThrown = false;
        try {
            $job->handle();
        } catch (RuntimeException) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'RuntimeException should be thrown');

        // Flag should be rolled back
        $this->assertNull($meeting->fresh()->started_notification_sent_at);
    }

    /** @test */
    public function failed_method_logs_meeting_id_and_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Meeting started notification job failed',
                Mockery::on(fn (array $context): bool => $context['meeting_id'] === 999 &&
                    $context['exception_class'] === RuntimeException::class &&
                    $context['exception_code'] === 0 &&
                    ! array_key_exists('exception', $context))
            );

        $job = new SendMeetingStartedNotification(
            meetingId: 999,
            notificationData: MeetingNotificationData::fromArray([])
        );

        $job->failed(new RuntimeException('Test failure'));

        $this->assertTrue(true);
    }

    /** @test */
    public function meeting_started_notification_does_not_implement_should_queue(): void
    {
        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([])
        );

        $this->assertNotInstanceOf(
            ShouldQueue::class,
            $notification,
            'MeetingStarted must not implement ShouldQueue to prevent nested queuing marking notifications sent prematurely.'
        );
    }

    /** @test */
    public function does_not_queue_downstream_notification_jobs(): void
    {
        Queue::fake();

        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        Notification::send($assignee, $notification);

        Queue::assertNotPushed(SendQueuedNotifications::class);
    }

    /** @test */
    public function meeting_started_notification_uses_custom_database_channel(): void
    {
        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([])
        );

        $channels = $notification->via(new User);

        $this->assertContains(DatabaseChannel::class, $channels);
        $this->assertNotContains('database', $channels);
    }

    /** @test */
    public function database_channel_deduplicates_notifications_on_retry(): void
    {
        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $channel = new DatabaseChannel;
        $channel->send($assignee, $notification);
        $channel->send($assignee, $notification);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $assignee->id,
            'type' => MeetingStarted::class,
        ]);
    }

    /** @test */
    public function database_channel_deduplicates_when_payload_differs_between_initial_send_and_recovery(): void
    {
        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $channel = new DatabaseChannel;

        // Initial send with full data
        $initialNotification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_id' => 42,
                'meeting_topic' => 'Test Topic 1',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ]),
            42,
        );
        $channel->send($assignee, $initialNotification);

        // Recovery redispatch where topic or start_time changed
        $recoveryNotification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_id' => 42,
                'meeting_topic' => 'Updated Topic Name',
                'project_name' => 'Project Renamed',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ]),
            42,
        );
        $channel->send($assignee, $recoveryNotification);

        // Still only 1 notification because meeting_id is identical
        $this->assertDatabaseCount('notifications', 1);
    }

    /** @test */
    public function database_channel_allows_different_meetings_with_identical_display_payloads(): void
    {
        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $channel = new DatabaseChannel;

        $meeting1Notification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_id' => 201,
                'meeting_topic' => 'Standup',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ]),
            201,
        );

        $meeting2Notification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_id' => 202,
                'meeting_topic' => 'Standup',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ]),
            202,
        );

        $channel->send($assignee, $meeting1Notification);
        $channel->send($assignee, $meeting2Notification);

        // Two distinct meetings produce two distinct notifications
        $this->assertDatabaseCount('notifications', 2);
    }

    /** @test */
    public function database_channel_produces_one_notification_per_recipient_per_event(): void
    {
        $assignee1 = User::factory()->create();
        $assignee2 = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee1);
        $this->inviteAndActivateUser($this->project, $assignee2);

        $channel = new DatabaseChannel;

        $startedData = MeetingNotificationData::fromArray([
            'meeting_id' => 77,
            'meeting_topic' => 'Design Review',
            'project_name' => 'Project',
            'notifier' => ['name' => 'Alice'],
            'meeting_join_url' => 'https://zoom.us/j/99',
            'start_time' => '2024-06-24T11:00:00Z',
            'meeting_timezone' => 'UTC',
            'project_slug' => 'test-project',
            'end_time' => null,
        ]);

        // Send started event to both recipients
        $channel->send($assignee1, new MeetingStarted($startedData, 77));
        $channel->send($assignee2, new MeetingStarted($startedData, 77));

        // Retry for both recipients (simulating job retry)
        $channel->send($assignee1, new MeetingStarted($startedData, 77));
        $channel->send($assignee2, new MeetingStarted($startedData, 77));

        // Each recipient gets exactly one notification — no duplicates on retry
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $assignee1->id, 'type' => MeetingStarted::class]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $assignee2->id, 'type' => MeetingStarted::class]);
    }

    /** @test */
    public function database_signature_is_derived_from_meeting_id_in_dto_when_no_explicit_id_passed(): void
    {
        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $channel = new DatabaseChannel;

        // meeting_id carried through DTO only — no explicit second constructor argument
        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_id' => 55,
                'meeting_topic' => 'Standup',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Bob'],
                'meeting_join_url' => 'https://zoom.us/j/55',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $channel->send($assignee, $notification);

        // Send again with different display data but same meeting (simulates recovery with renamed project)
        $recoveryNotification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_id' => 55,
                'meeting_topic' => 'Standup - Updated Topic',
                'project_name' => 'Project Renamed',
                'notifier' => ['name' => 'Bob'],
                'meeting_join_url' => 'https://zoom.us/j/55',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );
        $channel->send($assignee, $recoveryNotification);

        // DTO-only path should still deduplicate correctly
        $this->assertDatabaseCount('notifications', 1);
    }
}
