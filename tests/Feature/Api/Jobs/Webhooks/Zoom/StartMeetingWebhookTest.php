<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\Events\MeetingStatusUpdate;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Zoom\MeetingStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

class StartMeetingWebhookTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function notifies_project_members_on_meeting_start(): void
    {
        Notification::fake();
        Event::fake();

        $meeting = Meeting::factory()->create([
            'meeting_id' => 813,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            // Ensure the meeting isn't already started to avoid early return
            'status' => 'waiting',
        ]);

        $users = User::factory()->count(2)->create()->each(fn (User $user) => $this->inviteAndActivateUser($this->project, $user)
        );

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_start.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $payload = $fixture['payload'];
        $object = $payload['object'];
        $meetingId = $object['id'];
        $startTime = $object['start_time'] ?? null;

        $job = new StartMeetingWebhook([
            'meeting_id' => $meetingId,
            'start_time' => $startTime,
        ]);

        $job->handle();

        $this->assertEquals('started', $meeting->fresh()->status);
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);
        $expectedUrl = route('api.v1.projects.show', ['project' => $this->project]);

        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);

        Notification::assertSentTo($users, MeetingStarted::class, fn (MeetingStarted $notification, array $channels): bool => $channels === ['mail', 'database', 'broadcast']
            && $notification->toDatabase($this->user)['link'] === $expectedLink
            && $notification->toMail($this->user)->viewData['projectLink'] === $expectedUrl);
    }

    /** @test */
    public function allows_missing_start_time_without_failing(): void
    {
        Notification::fake();
        Event::fake();

        $meeting = Meeting::factory()->create([
            'meeting_id' => 813,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status' => 'waiting',
        ]);

        $users = User::factory()->count(2)->create()->each(fn (User $user) => $this->inviteAndActivateUser($this->project, $user)
        );

        $job = new StartMeetingWebhook([
            'meeting_id' => 813,
            'start_time' => null,
        ]);

        $job->handle();

        $this->assertEquals('started', $meeting->fresh()->status);
        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);
        Notification::assertSentTo($users, MeetingStarted::class);
    }

    /** @test */
    public function ignores_missing_meeting_without_failing(): void
    {
        Notification::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $job = new StartMeetingWebhook([
            'meeting_id' => 999999,
            'start_time' => null,
        ]);

        $job->handle();

        Notification::assertNothingSent();
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function does_not_send_duplicate_notifications_when_meeting_is_already_started(): void
    {
        Notification::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = Meeting::factory()->create([
            'meeting_id' => 813,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status' => 'started',
        ]);

        $job = new StartMeetingWebhook([
            'meeting_id' => 813,
            'start_time' => '2024-06-24T11:00:00Z',
        ]);

        $job->handle();

        $this->assertEquals('started', $meeting->fresh()->status);
        Notification::assertNothingSent();
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }
}
