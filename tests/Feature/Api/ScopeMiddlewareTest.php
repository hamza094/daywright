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

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Invalid ability provided.']);
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
}
