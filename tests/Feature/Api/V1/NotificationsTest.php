<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\AcceptInvitation;
use App\Notifications\ProjectInvitation;
use App\Notifications\ProjectTask;
use App\Notifications\ProjectUpdated;
use App\Notifications\UserMentioned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class NotificationsTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;
    /**
     * A notification feature test.
     */

    /** @test */
    public function invited_user_can_get_project_invitation(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->sendInvitationToUser($this->project, $user);

        Notification::assertSentTo($user, ProjectInvitation::class);
    }

    /** @test */
    public function project_owner_get_notified_by_member(): void
    {
        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create();

        $this->project->invite($user);

        Sanctum::actingAs($user);

        $this->postJson($this->project->path().'/invitations/accept');

        Notification::assertSentTo($this->project->user, AcceptInvitation::class);
    }

    /** @test */
    public function allowed_user_notified_on_project_update(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->addMember($this->project, $user);

        $this->patchJson($this->project->path(), ['notes' => 'Project notes updated.']);

        Notification::assertSentTo($user, ProjectUpdated::class);
    }

    /** @test */
    public function project_member_notified_when_task_added(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->addMember($this->project, $user);

        $this->postJson($this->project->path().'/tasks', ['title' => 'new task added']);

        Notification::assertSentTo($user, ProjectTask::class);
    }

    /** @test */
    public function mentioned_user_in_a_chat_are_notified(): void
    {
        Notification::fake();

        $newUser = User::factory(['username' => 'thanos844'])
            ->create();

        $this->addMember($this->project, $newUser);

        $this
            ->postJson($this->project->path().'/conversations', ['message' => 'random chat conversation with @thanos844',
                'user_id' => $this->user->id]);

        Notification::assertSentTo($newUser, UserMentioned::class);

        Notification::assertCount(1);
    }

    /** @test */
    public function user_should_not_receive_task_notification_when_adding_a_task(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->addMember($this->project, $user);

        Sanctum::actingAs($user);

        $this->postJson($this->project->path().'/task', ['body' => 'another new task added']);

        Notification::assertNotSentTo($user, ProjectTask::class);
    }

    protected function sendInvitationToUser($project, $user)
    {
        $this->postJson($this->project->path().'/invitations', [
            'email' => $user->email,
        ]);
    }

    protected function addMember($project, $user)
    {
        $this->project
            ->members()
            ->attach($user, ['active' => true]);
    }
}
