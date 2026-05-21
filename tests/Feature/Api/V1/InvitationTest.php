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

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('send.invitation', $this->project), [
            'email' => $invitedUser->email,
        ])->assertCreated()
            ->assertJsonPath('data.id', $this->project->id)
            ->assertJsonPath('data.slug', $this->project->slug)
            ->assertJsonPath('data.links.project', $this->apiV1ProjectRoute('projects.show', $this->project));

        $this->assertTrue($this->project->members->contains($invitedUser));
    }

    /** @test */
    public function project_owner_can_not_reinvite_user_and_himself(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();
        $this->project->invite($invitedUser);

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('send.invitation', $this->project), [
            'email' => $invitedUser->email,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('errors.invitation.0', 'Project invitation already sent to a user.');

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('send.invitation', $this->project),
            ['email' => $this->project->user->email])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('errors.invitation.0', "Can't send an invitation to the project owner.");
    }

    /** @test */
    public function it_allows_valid_email(): void
    {
        $user = User::factory()->create(['email' => 'valid@example.com']);

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('send.invitation', $this->project),
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

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('accept.invitation', $this->project))
            ->assertOk()
            ->assertJsonPath('data.project.id', $this->project->id)
            ->assertJsonPath('data.project.slug', $this->project->slug)
            ->assertJsonPath('data.project.links.self', $this->apiV1ProjectRoute('projects.show', $this->project))
            ->assertJsonPath('data.invitation_state', 'accepted');

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

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('accept.invitation', $this->project))
            ->assertForbidden();
    }

    /** @test */
    public function authorized_user_can_reject_project_invitation(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();

        $this->project->invite($invitedUser);

        Sanctum::actingAs($invitedUser);

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('reject.invitation', $this->project))
            ->assertOk()
            ->assertJsonPath('data.project.id', $this->project->id)
            ->assertJsonPath('data.project.slug', $this->project->slug)
            ->assertJsonPath('data.project.links.self', $this->apiV1ProjectRoute('projects.show', $this->project))
            ->assertJsonPath('data.invitation_state', 'rejected');

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

        $this->deleteJson($this->apiV1ProjectUserRoute('projects.members.destroy', $this->project, $memberUser))
            ->assertJson([
                'message' => "Member {$memberUser->name} has been removed from the project",
            ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $memberUser->id,
        ]);
    }

    /** @test */
    public function project_owner_can_repeat_member_removal_without_error(): void
    {
        /** @var User $memberUser */
        $memberUser = User::factory()->create();

        $this->project->members()->attach($memberUser, ['active' => true]);

        $route = $this->apiV1ProjectUserRoute('projects.members.destroy', $this->project, $memberUser);

        $this->deleteJson($route)
            ->assertOk()
            ->assertJson([
                'message' => "Member {$memberUser->name} has been removed from the project",
            ]);

        $this->deleteJson($route)
            ->assertOk()
            ->assertJson([
                'message' => "Member {$memberUser->name} has been removed from the project",
            ]);
    }

    /** @test */
    public function project_owner_cannot_remove_a_pending_invitation_from_members_endpoint(): void
    {
        /** @var User $pendingUser */
        $pendingUser = User::factory()->create();

        $this->project->invite($pendingUser);

        $this->deleteJson($this->apiV1ProjectUserRoute('projects.members.destroy', $this->project, $pendingUser))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('errors.user.0', 'This user is not an active member of the project.');
    }

    /** @test */
    public function project_owner_can_view_pending_member_invitations(): void
    {
        $pendingUsers = User::factory()->count(3)->create();

        $this->project
            ->members()
            ->attach($pendingUsers, ['active' => false]);

        $response = $this->getJson($this->apiV1ProjectRoute('project.pending.invitation', $this->project, query: [
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
    public function project_owner_can_view_pending_member_invitations_with_status_filter(): void
    {
        $pendingUsers = User::factory()->count(2)->create();

        $this->project
            ->members()
            ->attach($pendingUsers, ['active' => false]);

        $this->getJson($this->apiV1ProjectRoute('project.pending.invitation', $this->project, query: [
            'filter' => ['status' => 'pending'],
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function pending_project_invitations_requires_pending_status_filter(): void
    {
        $this->getJson($this->apiV1ProjectRoute('project.pending.invitation', $this->project))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter']);

        $this->getJson($this->apiV1ProjectRoute('project.pending.invitation', $this->project, query: [
            'filter' => ['status' => 'accepted'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.status']);
    }

    /** @test */
    public function pending_project_invitations_reject_unsupported_top_level_query_parameters(): void
    {
        $this->getJson($this->apiV1ProjectRoute('project.pending.invitation', $this->project, query: [
            'filter' => ['status' => 'pending'],
            'page' => 2,
            'include' => 'owner',
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page', 'include', 'random']);
    }

    /** @test */
    public function pending_project_invitations_reject_legacy_top_level_status_alias(): void
    {
        $this->getJson($this->apiV1ProjectRoute('project.pending.invitation', $this->project, query: [
            'status' => 'pending',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function pending_project_invitations_reject_unsupported_nested_filter_keys(): void
    {
        $this->getJson($this->apiV1ProjectRoute('project.pending.invitation', $this->project, query: [
            'filter' => ['state' => 'pending'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter']);
    }
}
