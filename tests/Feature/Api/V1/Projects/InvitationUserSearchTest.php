<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class InvitationUserSearchTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function project_manager_can_search_for_invitable_users(): void
    {
        $otherUser = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'john',
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'name', 'username', 'email', 'avatar_path'],
                ],
            ])
            ->assertJsonFragment(['name' => 'John Doe'])
            ->assertJsonFragment(['email' => 'john@example.com']);
    }

    /** @test */
    public function search_excludes_project_owner(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => mb_substr($this->user->name, 0, 3),
            ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function search_excludes_existing_project_members(): void
    {
        $existingMember = User::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        $this->project->members()->attach($existingMember->id, ['active' => true]);

        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'alice',
            ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function search_requires_minimum_2_characters(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'j',
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    /** @test */
    public function search_validates_maximum_100_characters(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => str_repeat('a', 101),
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    /** @test */
    public function search_requires_search_parameter(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    /** @test */
    public function non_project_manager_cannot_search_users(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'test',
            ]));

        $response->assertForbidden();
    }

    /** @test */
    public function search_returns_empty_array_when_no_matches(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'nonexistentuser',
            ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function search_limits_results_to_5_users(): void
    {
        User::factory()->count(10)->create(['name' => 'Test User']);

        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'test',
            ]));

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function search_by_email_works(): void
    {
        User::factory()->create(['email' => 'searchuser@example.com']);

        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'searchuser',
            ]));

        $response->assertOk()
            ->assertJsonFragment(['email' => 'searchuser@example.com']);
    }

    /** @test */
    public function search_normalizes_whitespace(): void
    {
        User::factory()->create(['name' => 'Test User']);

        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => '  test   user  ',
            ]));

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Test User']);
    }

    /** @test */
    public function search_uses_starts_with_pattern(): void
    {
        User::factory()->create(['name' => 'Test User']);
        User::factory()->create(['name' => 'Another Test User']);

        $response = $this->actingAs($this->user)
            ->getJson($this->apiV1Route('projects.users.search', [
                'project' => $this->project->slug,
                'search' => 'test',
            ]));

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Test User'])
            ->assertJsonMissing(['name' => 'Another Test User']);
    }
}
