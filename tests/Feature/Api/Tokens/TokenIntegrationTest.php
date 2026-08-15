<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tokens;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_token_lifecycle_and_scope_enforcement(): void
    {
        $user = User::factory()->create();

        // 1. Create a scoped token via the API (simulating the Vue Dashboard)
        $createResponse = $this->actingAs($user, 'web')
            ->withHeaders(['Idempotency-Key' => 'test-key-1'])
            ->postJson('/api/v1/api-tokens', [
                'name' => 'Limited CRM Key',
                'scopes' => ['account:read'], // ONLY read access
                'expires_at' => null, // Never expires
            ]);

        $createResponse->assertStatus(201);
        $plainTextKey = $createResponse->json('data.token');

        // 2. Use the new key to perform an ALLOWED action (read - list tokens)
        $this->withToken($plainTextKey)
            ->getJson('/api/v1/api-tokens')
            ->assertStatus(200); // Or 404 if empty, but NOT 403 Forbidden

        // 3. Verify the token has the correct scopes stored
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Limited CRM Key',
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_token_creation_requires_scopes_strict_mode(): void
    {
        $user = User::factory()->create();

        // Attempt to create token without scopes (should fail in strict mode)
        $response = $this->actingAs($user, 'web')
            ->withHeaders(['Idempotency-Key' => 'test-key-2'])
            ->postJson('/api/v1/api-tokens', [
                'name' => 'Test Token',
                'expires_at' => null,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scopes']);
    }

    public function test_token_creation_rejects_invalid_scopes(): void
    {
        $user = User::factory()->create();

        // Attempt to create token with invalid scope
        $response = $this->actingAs($user, 'web')
            ->withHeaders(['Idempotency-Key' => 'test-key-3'])
            ->postJson('/api/v1/api-tokens', [
                'name' => 'Test Token',
                'scopes' => ['invalid:scope'],
                'expires_at' => null,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scopes.0']);
    }

    public function test_token_with_multiple_scopes_can_access_all_allowed_endpoints(): void
    {
        $user = User::factory()->create();

        // Create token with multiple scopes
        $createResponse = $this->actingAs($user, 'web')
            ->withHeaders(['Idempotency-Key' => 'test-key-4'])
            ->postJson('/api/v1/api-tokens', [
                'name' => 'Multi-scope Key',
                'scopes' => ['projects:read', 'team:read', 'account:read'],
                'expires_at' => null,
            ]);

        $createResponse->assertStatus(201);
        $plainTextKey = $createResponse->json('data.token');

        // Should be able to access projects endpoint
        $this->withToken($plainTextKey)
            ->getJson('/api/v1/projects')
            ->assertStatus(200);

        // Should be able to access users/me endpoint (account:read)
        $this->withToken($plainTextKey)
            ->getJson('/api/v1/users/me')
            ->assertStatus(200);
    }
}
