<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class UserInvitationTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function it_returns_pending_project_invitations_for_authenticated_user(): void
    {
        // Create a project and attach as pending invitation
        $project = project::factory()->create();

        $project->members()->attach($this->user->id, ['active' => false, 'created_at' => now(), 'updated_at' => now()]);
        $pivot = $project->members()->whereKey($this->user->id)->firstOrFail()->pivot;

        $response = $this->getJson('/api/v1/me/invitations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'status', 'slug', 'invitation_sent_at', 'created_at', 'links' => ['project']],
                ],
            ]);

        $this->assertEquals($project->id, $response->json('data.0.id'));
        $this->assertEquals($project->path(), $response->json('data.0.links.project'));
        $this->assertEquals($project->created_at?->setTimezone('UTC')->toIso8601String(), $response->json('data.0.created_at'));
        $this->assertEquals($pivot->created_at?->setTimezone('UTC')->toIso8601String(), $response->json('data.0.invitation_sent_at'));
    }

    /** @test */
    public function it_returns_empty_array_and_message_if_no_pending_invitations(): void
    {

        $response = $this->getJson('/api/v1/me/invitations');

        $response->assertStatus(200)
            ->assertExactJson([
                'data' => [],
            ]);
    }
}
