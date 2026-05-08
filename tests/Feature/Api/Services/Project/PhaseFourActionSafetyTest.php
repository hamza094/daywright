<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Services\Project;

use App\Actions\Project\AcceptProjectInvitationAction;
use App\Actions\Task\AssignTaskMembersAction;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\AcceptInvitation;
use App\Notifications\TaskAssigned;
use App\Services\Project\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class PhaseFourActionSafetyTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    /** @test */
    public function assign_task_members_action_only_notifies_new_assignees_once(): void
    {
        Notification::fake();

        $task = $this->project->addTask('test task');
        $member = User::factory()->create();

        $member->members()->syncWithoutDetaching([
            $this->project->id => ['active' => true],
        ]);

        $action = app(AssignTaskMembersAction::class);

        $action->execute($task, $this->project, $this->user, [$member->id]);
        $action->execute($task, $this->project, $this->user, [$member->id]);

        $this->assertSame(1, DB::table('task_user')
            ->where('task_id', $task->id)
            ->where('user_id', $member->id)
            ->count());

        Notification::assertSentToTimes($member, TaskAssigned::class, 1);
    }

    /** @test */
    public function accept_project_invitation_action_is_safe_to_repeat(): void
    {
        Notification::fake();

        $invitedUser = User::factory()->create();

        $this->project->invite($invitedUser);

        $action = app(AcceptProjectInvitationAction::class);

        $action->execute($this->project, $invitedUser);
        $action->execute($this->project, $invitedUser);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $invitedUser->id,
            'active' => true,
        ]);

        Notification::assertSentToTimes($this->user, AcceptInvitation::class, 1);
    }

    /** @test */
    public function meeting_service_keeps_the_persisted_zoom_meeting_id_during_updates(): void
    {
        $meeting = Meeting::factory()
            ->for($this->project)
            ->for($this->user)
            ->create([
                'meeting_id' => 1234,
                'topic' => 'Original topic',
            ]);

        $zoom = Mockery::mock(Zoom::class);
        $zoom->shouldReceive('updateMeeting')
            ->once()
            ->with(
                Mockery::on(fn (array $payload): bool => $payload === [
                    'topic' => 'Updated topic',
                    'meeting_id' => 1234,
                ]),
                Mockery::on(fn (User $user): bool => $user->is($this->user)),
            )
            ->andReturnTrue();

        $service = app(MeetingService::class);

        $updatedMeeting = $service->updateProjectMeeting($meeting, $this->user, [
            'meeting_id' => 9999,
            'topic' => 'Updated topic',
        ], $zoom);

        $this->assertSame(1234, $updatedMeeting->meeting_id);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'meeting_id' => 1234,
            'topic' => 'Updated topic',
        ]);
    }
}
