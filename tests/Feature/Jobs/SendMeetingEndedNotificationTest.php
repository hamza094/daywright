<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Channels\DatabaseChannel;
use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\Jobs\SendMeetingEndedNotification;
use App\Models\User;
use App\Notifications\Zoom\MeetingEnded;
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

class SendMeetingEndedNotificationTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function does_not_send_when_flag_is_already_set(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
            'ended_notification_sent_at' => now(),
        ]);

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
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
            'status' => 'ended',
            'ended_notification_sent_at' => null,
            'ended_notification_pending_at' => now(),
        ]);

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
            ])
        );

        $job->handle();

        Notification::assertSentTo($assignee, MeetingEnded::class);
        $meeting = $meeting->fresh();
        $this->assertNotNull($meeting->ended_notification_sent_at);
        $this->assertNull($meeting->ended_notification_pending_at);
    }

    /** @test */
    public function does_not_claim_if_already_claimed_by_another_worker(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
            'ended_notification_sent_at' => null,
        ]);

        // Simulate another worker claiming the flag
        $meeting->update(['ended_notification_sent_at' => now()]);

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
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
            'status' => 'ended',
            'ended_notification_sent_at' => null,
        ]);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Notification service failed'));

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
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
        $this->assertNull($meeting->fresh()->ended_notification_sent_at);
    }

    /** @test */
    public function failed_method_logs_meeting_id_and_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Meeting ended notification job failed',
                Mockery::on(fn (array $context): bool => $context['meeting_id'] === 999 &&
                    $context['exception_class'] === RuntimeException::class &&
                    $context['exception_code'] === 0 &&
                    ! array_key_exists('exception', $context))
            );

        $job = new SendMeetingEndedNotification(
            meetingId: 999,
            notificationData: MeetingNotificationData::fromArray([])
        );

        $job->failed(new RuntimeException('Test failure'));

        $this->assertTrue(true);
    }

    /** @test */
    public function meeting_ended_notification_does_not_implement_should_queue(): void
    {
        $notification = new MeetingEnded(
            MeetingNotificationData::fromArray([])
        );

        $this->assertNotInstanceOf(
            ShouldQueue::class,
            $notification,
            'MeetingEnded must not implement ShouldQueue to prevent nested queuing marking notifications sent prematurely.'
        );
    }

    /** @test */
    public function does_not_queue_downstream_notification_jobs(): void
    {
        Queue::fake();

        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $notification = new MeetingEnded(
            MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
            ])
        );

        Notification::send($assignee, $notification);

        Queue::assertNotPushed(SendQueuedNotifications::class);
    }

    /** @test */
    public function meeting_ended_notification_uses_custom_database_channel(): void
    {
        $notification = new MeetingEnded(
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

        $notification = new MeetingEnded(
            MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
            ])
        );

        $channel = new DatabaseChannel;
        $channel->send($assignee, $notification);
        $channel->send($assignee, $notification);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $assignee->id,
            'type' => MeetingEnded::class,
        ]);
    }

    /** @test */
    public function database_channel_deduplicates_when_payload_differs_between_initial_send_and_recovery(): void
    {
        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $channel = new DatabaseChannel;

        // Initial send with full end_time
        $initialNotification = new MeetingEnded(
            MeetingNotificationData::fromArray([
                'meeting_id' => 42,
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
            ]),
            42,
        );
        $channel->send($assignee, $initialNotification);

        // Recovery redispatch with end_time = null (different payload / message string)
        $recoveryNotification = new MeetingEnded(
            MeetingNotificationData::fromArray([
                'meeting_id' => 42,
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => null,
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
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

        $meeting1Notification = new MeetingEnded(
            MeetingNotificationData::fromArray([
                'meeting_id' => 101,
                'meeting_topic' => 'Standup',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
            ]),
            101,
        );

        $meeting2Notification = new MeetingEnded(
            MeetingNotificationData::fromArray([
                'meeting_id' => 102,
                'meeting_topic' => 'Standup',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
            ]),
            102,
        );

        $channel->send($assignee, $meeting1Notification);
        $channel->send($assignee, $meeting2Notification);

        // Two distinct meetings produce two distinct notifications
        $this->assertDatabaseCount('notifications', 2);
    }
}
