<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\Events\MeetingStatusUpdate;
use App\Jobs\Webhooks\Zoom\MeetingEndsWebhook;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Zoom\MeetingEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
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

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_ended.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $object = $fixture['payload']['object'];
        $meetingId = (string) $object['id'];
        $startTime = $object['start_time'] ?? null;
        $endTime = $object['end_time'] ?? null;

        $job = new MeetingEndsWebhook([
            'meeting_id' => $meetingId,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        $job->handle();

        $this->assertEquals('ended', $meeting->fresh()->status);
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);
        $expectedUrl = route('api.v1.projects.show', ['project' => $this->project]);

        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);

        Notification::assertSentTo($users, MeetingEnded::class, fn (MeetingEnded $notification, array $channels): bool => $channels === ['mail', 'database', 'broadcast']
            && $notification->toArray($this->user)['link'] === $expectedLink
            && $notification->toMail($this->user)->viewData['projectLink'] === $expectedUrl);
    }

    /** @test */
    public function allows_missing_optional_timestamps_without_failing(): void
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

        $job = new MeetingEndsWebhook([
            'meeting_id' => 813,
            'start_time' => null,
            'end_time' => null,
        ]);

        $job->handle();

        $this->assertEquals('ended', $meeting->fresh()->status);
        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);
        Notification::assertSentTo($users, MeetingEnded::class);
    }

    /** @test */
    public function ignores_missing_meeting_without_failing(): void
    {
        Notification::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $job = new MeetingEndsWebhook([
            'meeting_id' => 999999,
            'start_time' => null,
            'end_time' => null,
        ]);

        $job->handle();

        Notification::assertNothingSent();
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function does_not_send_duplicate_notifications_when_meeting_is_already_ended(): void
    {
        Notification::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = Meeting::factory()->create([
            'meeting_id' => 813,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status' => 'ended',
        ]);

        $job = new MeetingEndsWebhook([
            'meeting_id' => 813,
            'start_time' => '2024-06-24T11:00:00Z',
            'end_time' => '2024-06-24T12:00:00Z',
        ]);

        $job->handle();

        $this->assertEquals('ended', $meeting->fresh()->status);
        Notification::assertNothingSent();
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }
}
