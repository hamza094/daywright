<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\Api\V1\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class SearchableTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    #[Test]
    public function it_returns_an_empty_collection_when_no_query_is_provided(): void
    {
        $result = $this->invitationService()->usersSearch($this->project, '   ');

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_searches_for_users_by_name_or_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Searchable User',
            'email' => 'searchable-user@example.com',
        ]);

        $result = $this->invitationService()->usersSearch($this->project, 'Search');

        $this->assertCount(1, $result);
        $this->assertSame($user->id, $result->first()?->id);
    }

    #[Test]
    public function test_search_returns_filtered_users(): void
    {
        $searchableUser = User::factory()->create([
            'name' => 'Test Candidate',
            'email' => 'test-candidate@example.com',
        ]);

        $pendingUser = User::factory()->create([
            'name' => 'Test Pending',
            'email' => 'test-pending@example.com',
        ]);

        $memberUser = User::factory()->create([
            'name' => 'Test Member',
            'email' => 'test-member@example.com',
        ]);

        $this->project->invite($pendingUser);
        $this->project->members()->attach($memberUser, ['active' => true]);

        $searchTerm = 'Test';

        $response = $this->withoutExceptionHandling()->getJson(route('projects.users.search', [
            'project' => $this->project,
            'query' => $searchTerm,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'uuid', 'name', 'username', 'email'],
            ])
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'uuid' => $searchableUser->uuid,
                'name' => $searchableUser->name,
                'email' => $searchableUser->email,
            ]);

    }

    private function invitationService(): InvitationService
    {
        return $this->app->make(InvitationService::class);
    }
}
