<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Traits\ProjectSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function project_owner_can_invite_user(): void
    {
        /** @var User $invitedUser */
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();

        $this->postJson($this->project->path().'/invitations', [
            'email' => $invitedUser->email,
        ])->assertOk()
            ->assertJson([
                'message' => "Project invitation sent to {$invitedUser->name}",
            ]);

        $this->assertTrue($this->project->members->contains($invitedUser));
    }

    /** @test */
    public function project_owner_can_not_reinvite_user_and_himself(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();
        $this->project->invite($invitedUser);

        $this->postJson($this->project->path().'/invitations', [
            'email' => $invitedUser->email,
        ])
            ->assertUnprocessable();

        $this->postJson($this->project->path().'/invitations',
            ['email' => $this->project->user->email])
            ->assertUnprocessable();
    }

    /** @test */
    public function it_allows_valid_email(): void
    {
        $user = User::factory()->create(['email' => 'valid@example.com']);

        $response = $this->postJson($this->project->path().'/invitations',
            ['email' => $user->email]);

        $response->assertStatus(200);
        $response->assertJsonMissingValidationErrors(['email']);
    }

    /** @test */
    public function auth_user_accept_project_invitation_sent_to_him(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();
        $this->project->invite($invitedUser);

        Sanctum::actingAs($invitedUser);

        $this->getJson($this->project->path().
            '/accept-invitation')
            ->assertJson([
                'message' => 'You have accepted Project invitation',
                'project' => ['id' => $this->project->id],
            ]);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $invitedUser->id,
            'active' => true,
        ]);
    }

    /** @test */
    public function uninvited_user_cannot_accept_invitation(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson($this->project->path().
            '/accept-invitation')
            ->assertForbidden();
    }

    /** @test */
    public function authorized_user_can_reject_project_invitation(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();

        $this->project->invite($invitedUser);

        Sanctum::actingAs($invitedUser);

        $this->getJson($this->project->path().'/reject/invitation')
            ->assertJson([
                'message' => 'You have rejected the invitation to join the project.',
                'project' => ['id' => $this->project->id],
            ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $invitedUser->id,
        ]);
    }

    /** @test */
    public function project_owner_can_cancel_project_invitation(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();

        $this->getJson(route('projects.cancel-invitation',
            ['project' => $this->project, 'user' => $invitedUser,
            ]))
            ->assertForbidden();

        $this->project->invite($invitedUser);

        $this->getJson(route('projects.cancel-invitation',
            ['project' => $this->project, 'user' => $invitedUser,
            ]))
            ->assertJson([
                'message' => 'You have canceled the invitation for '.$invitedUser->name.' to join the project.',
                'project' => ['id' => $this->project->id],
            ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $invitedUser->id,
        ]);
    }

    /** @test */
    public function project_owner_can_remove_member(): void
    {
        /** @var User $memberUser */
        $memberUser = User::factory()->create();

        $this->project->members()->attach($memberUser, ['active' => true]);

        $this->getJson($this->project->path().'/remove/member/'.$memberUser->uuid)
            ->assertJson([
                'message' => "Member {$memberUser->name} has been removed from the project",
            ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $memberUser->id,
        ]);
    }

    /** @test */
    public function project_owner_can_view_pending_member_invitations(): void
    {
        $pendingUsers = User::factory()->count(3)->create();

        $this->project
            ->members()
            ->attach($pendingUsers, ['active' => false]);

        $response = $this->getJson($this->project->path().'/pending/invitations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'pending_invitations' => [
                    '*' => [
                        'uuid',
                        'name',
                        'username',
                        'email',
                        'invitation_sent_at',
                        'links' => [
                            'self',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'message' => 'List of project pending member requests',
            ]);

        // Assert the count of pending invitations
        $this->assertCount(3, $response->json('pending_invitations'));

    }
}
