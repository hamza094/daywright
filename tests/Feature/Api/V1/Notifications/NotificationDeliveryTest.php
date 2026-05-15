<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Notifications;

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
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

class NotificationDeliveryTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function invited_user_can_get_project_invitation(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);

        $this->sendInvitationToUser($this->project, $user);

        Notification::assertSentTo($user, ProjectInvitation::class, fn (ProjectInvitation $notification): bool => $notification->toArray($user)['link'] === $expectedLink);
    }

    /** @test */
    public function project_owner_get_notified_by_member(): void
    {
        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create();
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);

        $this->project->invite($user);

        Sanctum::actingAs($user);

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('accept.invitation', $this->project));

        Notification::assertSentTo($this->project->user, AcceptInvitation::class, fn (AcceptInvitation $notification): bool => $notification->toArray($this->project->user)['link'] === $expectedLink);
    }

    /** @test */
    public function allowed_user_notified_on_project_update(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);

        $this->addMember($this->project, $user);

        $this->patchJson($this->apiV1ProjectRoute('projects.update', $this->project), ['notes' => 'Project notes updated.']);

        Notification::assertSentTo($user, ProjectUpdated::class, fn (ProjectUpdated $notification): bool => $notification->toArray($user)['link'] === $expectedLink);
        Notification::assertNotSentTo($this->user, ProjectUpdated::class);
    }

    /** @test */
    public function project_member_notified_when_task_added(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);

        $this->addMember($this->project, $user);

        $this->postJson($this->apiV1ProjectRoute('tasks.store', $this->project), ['title' => 'new task added']);

        Notification::assertSentTo($user, ProjectTask::class, fn (ProjectTask $notification): bool => $notification->toArray($user)['link'] === $expectedLink);
    }

    /** @test */
    public function mentioned_user_in_a_chat_are_notified(): void
    {
        Notification::fake();

        $newUser = User::factory(['username' => 'thanos844'])
            ->create();
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);

        $this->addMember($this->project, $newUser);

        $this
            ->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), ['message' => 'random chat conversation with @thanos844',
                'user_id' => $this->user->id]);

        Notification::assertSentTo($newUser, UserMentioned::class, fn (UserMentioned $notification): bool => $notification->toArray($newUser)['link'] === $expectedLink);

        Notification::assertCount(1);
    }

    /** @test */
    public function user_should_not_receive_task_notification_when_adding_a_task(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->addMember($this->project, $user);

        Sanctum::actingAs($user);

        $this->postJson($this->apiV1ProjectRoute('projects.show', $this->project).'/task', ['body' => 'another new task added']);

        Notification::assertNotSentTo($user, ProjectTask::class);
    }
}
