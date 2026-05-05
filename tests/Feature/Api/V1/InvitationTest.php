<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class InvitationTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function project_owner_can_invite_user(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();

        $this->postJson($this->project->path().'/invitations', [
            'email' => $invitedUser->email,
        ])->assertCreated()
            ->assertJsonPath('data.id', $this->project->id)
            ->assertJsonPath('data.slug', $this->project->slug)
            ->assertJsonPath('data.links.project', $this->project->path());

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
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation Error')
            ->assertJsonPath('errors.invitation.0', 'Project invitation already sent to a user.');

        $this->postJson($this->project->path().'/invitations',
            ['email' => $this->project->user->email])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation Error')
            ->assertJsonPath('errors.invitation.0', "Can't send an invitation to the project owner.");
    }

    /** @test */
    public function it_allows_valid_email(): void
    {
        $user = User::factory()->create(['email' => 'valid@example.com']);

        $response = $this->postJson($this->project->path().'/invitations',
            ['email' => $user->email]);

        $response->assertCreated();
        $response->assertJsonMissingValidationErrors(['email']);
    }

    /** @test */
    public function auth_user_accept_project_invitation_sent_to_him(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();
        $this->project->invite($invitedUser);

        Sanctum::actingAs($invitedUser);

        $this->postJson($this->project->path().
            '/invitations/accept')
            ->assertJsonPath('message', 'You have accepted Project invitation');

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

        $this->postJson($this->project->path().
            '/invitations/accept')
            ->assertForbidden();
    }

    /** @test */
    public function authorized_user_can_reject_project_invitation(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();

        $this->project->invite($invitedUser);

        Sanctum::actingAs($invitedUser);

        $this->postJson($this->project->path().'/invitations/reject')
            ->assertJsonPath('message', 'You have rejected the invitation to join the project.');

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

        $this->deleteJson(route('api.v1.projects.cancel-invitation',
            ['project' => $this->project, 'user' => $invitedUser,
            ]))
            ->assertForbidden();

        $this->project->invite($invitedUser);

        $this->deleteJson(route('api.v1.projects.cancel-invitation',
            ['project' => $this->project, 'user' => $invitedUser,
            ]))
            ->assertJson([
                'message' => 'You have canceled the invitation for '.$invitedUser->name.' to join the project.',
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

        $this->deleteJson($this->project->path().'/members/'.$memberUser->uuid)
            ->assertJson([
                'message' => "Member {$memberUser->name} has been removed from the project",
            ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $memberUser->id,
        ]);
    }

    /** @test */
    public function project_owner_cannot_remove_a_user_who_is_not_an_active_member(): void
    {
        /** @var User $nonMember */
        $nonMember = User::factory()->create();

        $this->deleteJson($this->project->path().'/members/'.$nonMember->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation Error')
            ->assertJsonPath('errors.user.0', 'This user is not an active member of the project.');
    }

    /** @test */
    public function project_owner_can_view_pending_member_invitations(): void
    {
        $pendingUsers = User::factory()->count(3)->create();

        $this->project
            ->members()
            ->attach($pendingUsers, ['active' => false]);

        $response = $this->getJson($this->project->path().'/invitations?'.http_build_query([
            'filter' => ['status' => 'pending'],
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
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
            ]);

        foreach ($response->json('data') as $invitation) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
                $invitation['invitation_sent_at']
            );
        }

        // Assert the count of pending invitations
        $this->assertCount(3, $response->json('data'));

    }

    /** @test */
    public function pending_project_invitations_requires_pending_status_filter(): void
    {
        $this->getJson($this->project->path().'/invitations')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter']);

        $this->getJson($this->project->path().'/invitations?'.http_build_query([
            'filter' => ['status' => 'accepted'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.status']);
    }
}
