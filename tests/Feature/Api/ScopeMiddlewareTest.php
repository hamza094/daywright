<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_requests_without_required_scope(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token', ['team:read']);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/api-tokens');

        // Token routes are now session-only, so API tokens are rejected
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Token management operations are only available via the web dashboard. Please use session-based authentication.']);
    }

    public function test_it_allows_session_requests_without_token_scope(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/api-tokens');

        // Session-based requests should pass tokenAbility middleware
        // (it only checks scopes for bearer token requests)
        $this->assertNotEquals(403, $response->status());
    }

    public function test_it_allows_requests_with_required_scope(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token', ['projects:read']);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/projects');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_account_read_token_blocked_from_post_projects(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token', ['account:read']);

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/projects', [
                'name' => 'Test Project',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Invalid ability provided.']);
    }

    public function test_read_only_token_blocked_from_write_operations(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token', ['projects:read']);

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/projects', [
                'name' => 'Test Project',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Invalid ability provided.']);
    }

    public function test_dashboard_requires_projects_read_not_account_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token', ['account:read']);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/dashboard/projects');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Invalid ability provided.']);
    }

    public function test_dashboard_allows_projects_read_scope(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token', ['projects:read']);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/dashboard/projects');

        $this->assertNotEquals(403, $response->status());
    }
}
