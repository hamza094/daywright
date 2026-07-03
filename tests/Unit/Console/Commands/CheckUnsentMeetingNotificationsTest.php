<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Enums\MeetingState;
use App\Jobs\SendMeetingEndedNotification;
use App\Jobs\SendMeetingStartedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class CheckUnsentMeetingNotificationsTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function re_dispatches_stuck_started_notifications(): void
    {
        Bus::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => MeetingState::START->value,
            'started_notification_sent_at' => null,
            'updated_at' => now()->subMinutes(15),
        ]);

        $this->artisan('meetings:check-unsent-notifications')->assertSuccessful();

        Bus::assertDispatched(SendMeetingStartedNotification::class, fn (SendMeetingStartedNotification $job): bool => $job->meetingId === $meeting->id);
    }

    /** @test */
    public function re_dispatches_stuck_ended_notifications(): void
    {
        Bus::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => MeetingState::ENDS->value,
            'ended_notification_sent_at' => null,
            'updated_at' => now()->subMinutes(15),
        ]);

        $this->artisan('meetings:check-unsent-notifications')->assertSuccessful();

        Bus::assertDispatched(SendMeetingEndedNotification::class, fn (SendMeetingEndedNotification $job): bool => $job->meetingId === $meeting->id);
    }

    /** @test */
    public function skips_recent_meetings_within_10_minute_window(): void
    {
        Bus::fake();

        MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => MeetingState::START->value,
            'started_notification_sent_at' => null,
            'updated_at' => now()->subMinutes(5),
        ]);

        $this->artisan('meetings:check-unsent-notifications')->assertSuccessful();

        Bus::assertNotDispatched(SendMeetingStartedNotification::class);
    }

    /** @test */
    public function skips_meetings_with_notification_already_sent(): void
    {
        Bus::fake();

        MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => MeetingState::START->value,
            'started_notification_sent_at' => now(),
            'updated_at' => now()->subMinutes(15),
        ]);

        $this->artisan('meetings:check-unsent-notifications')->assertSuccessful();

        Bus::assertNotDispatched(SendMeetingStartedNotification::class);
    }

    /** @test */
    public function skips_meetings_belonging_to_deleted_projects(): void
    {
        Bus::fake();

        MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => MeetingState::START->value,
            'started_notification_sent_at' => null,
            'updated_at' => now()->subMinutes(15),
        ]);

        $this->project->delete();

        $this->artisan('meetings:check-unsent-notifications')->assertSuccessful();

        Bus::assertNotDispatched(SendMeetingStartedNotification::class);
    }
}
