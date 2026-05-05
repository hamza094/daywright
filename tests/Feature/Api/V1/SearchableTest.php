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

        $result = $service->usersSearch('missing-user');

        $this->assertTrue($result->isEmpty());
    }

    /** @test */
    public function it_searches_for_users_by_name_or_email(): void
    {
        $user = User::first();

        $query = $user->name;

        $service = app(InvitationService::class);
        $result = $service->usersSearch($query);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_search_returns_filtered_users(): void
    {
        User::factory(5)->create(['name' => 'Test User']);

        User::factory(3)->create(['name' => 'Other User']);

        $searchTerm = 'Test';

        // Act
        $response = $this->withoutExceptionHandling()->getJson(route('api.v1.users.search', [
            'query' => $searchTerm,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'name', 'email'],
                ],
            ])
            ->assertJsonCount(5, 'data');

    }
}
