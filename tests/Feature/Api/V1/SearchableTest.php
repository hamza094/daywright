<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\Api\V1\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class SearchableTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /**
     * A search feature test example.
     */
    public function it_returns_an_empty_collection_when_no_query_is_provided(): void
    {
        $request = new Request;

        $service = new InvitationService;

        $result = $service->usersSearch($request);

        $this->assertTrue($result->isEmpty());
    }

    /** @test */
    public function it_searches_for_users_by_name_or_email(): void
    {
        $user = User::first();

        $query = $user->name;
        $request = new Request(['query' => $query]);

        $service = new InvitationService;
        $result = $service->usersSearch($request);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_search_returns_filtered_users(): void
    {
        User::factory(5)->create(['name' => 'Test User']);

        User::factory(3)->create(['name' => 'Other User']);

        $searchTerm = 'Test';

        // Act
        $response = $this->withoutExceptionHandling()->getJson(route('users.search', [
            'query' => $searchTerm,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['uuid', 'name', 'email'],

            ])
            ->assertJsonCount(5); // Ensure only the matching users are returned*/

    }
}
