<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\Project\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class SearchableTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function it_returns_an_empty_collection_when_no_users_match_query(): void
    {
        $service = app(InvitationService::class);

        $result = $service->usersSearch($this->project, 'missing-user');

        $this->assertTrue($result->isEmpty());
    }

    /** @test */
    public function it_searches_for_users_by_name_or_email(): void
    {
        // Create a user that can be invited (not the project owner or member)
        User::factory()->create(['name' => 'Searchable User']);

        $searchTerm = 'Searchable';

        $service = app(InvitationService::class);
        $result = $service->usersSearch($this->project, $searchTerm);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_search_returns_filtered_users(): void
    {
        User::factory(5)->create(['name' => 'Test User']);

        User::factory(3)->create(['name' => 'Other User']);

        $searchTerm = 'Test';

        // Act
        $response = $this->withoutExceptionHandling()->getJson($this->apiV1Route('projects.users.search', [
            'project' => $this->project->slug,
            'search' => $searchTerm,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'name', 'email'],
                ],
            ])
            ->assertJsonCount(5, 'data');

    }

    /** @test */
    public function search_endpoint_rejects_legacy_query_alias(): void
    {
        $this->getJson($this->apiV1Route('projects.users.search', [
            'project' => $this->project->slug,
            'query' => 'Test',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    /** @test */
    public function search_endpoint_rejects_unsupported_top_level_query_parameters(): void
    {
        $this->getJson($this->apiV1Route('projects.users.search', [
            'project' => $this->project->slug,
            'search' => 'Test',
            'sort' => 'name',
            'include' => 'projects',
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'include', 'random']);
    }
}
