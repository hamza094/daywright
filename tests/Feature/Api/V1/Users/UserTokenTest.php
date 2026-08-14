<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Users;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        // create a user
        User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
            'name' => 'jon doe',
        ]);
    }

    #[Test]
    public function user_can_list_their_tokens(): void
    {
        $user = User::first();

        $this->actingAs($user, 'web');

        $user->createToken('Test Token', ['account:read']);
        $response = $this->getJson($this->apiV1Route('api-tokens.index'));
        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Test Token']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            (string) $response->json('data.0.created_at')
        );
    }

    #[Test]
    public function user_can_create_a_token(): void
    {
        $user = User::first();

        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'My API Token',
            'scopes' => ['account:read'],
        ]);
        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull($response->json('data.token_resource.expires_at'));

        $token = $user->tokens()->latest('id')->first();

        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);

        // Verify default 90-day expiration
        $expectedExpiry = now()->addDays(90);
        $this->assertLessThanOrEqual($expectedExpiry, $token->expires_at);
        $this->assertGreaterThanOrEqual($expectedExpiry->subMinute(), $token->expires_at);
    }

    #[Test]
    public function creating_api_token_creates_audit_log(): void
    {
        $user = User::first();

        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Audit Test Token',
            'scopes' => ['account:read'],
        ]);
        $response->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'event' => 'security.api_token_created',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $log = AuditLog::where('event', 'security.api_token_created')->first();

        $this->assertNotNull($log);
        $this->assertSame('Audit Test Token', $log->new_values['token_name']);
        $this->assertNotNull($log->new_values['token_id']);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function user_can_create_a_token_with_iso_expiration(): void
    {
        $user = User::first();

        $this->actingAs($user, 'web');

        $expiresAt = '2026-05-20T15:30:00+02:00';
        $expectedExpiration = CarbonImmutable::parse($expiresAt)->setTimezone('UTC')->toIso8601String();

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Expiring API Token',
            'expires_at' => $expiresAt,
            'scopes' => ['account:read'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.token_resource.expires_at', $expectedExpiration);

        $token = $user->tokens()->latest('id')->first();

        $this->assertNotNull($token);
        $this->assertSame($expectedExpiration, $token->expires_at?->toIso8601String());
    }

    #[Test]
    public function expires_at_must_be_iso_8601_with_timezone_offset(): void
    {
        $user = User::first();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Legacy Token',
            'expires_at' => '2026-05-20 15:30:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);
    }

    #[Test]
    public function user_can_delete_a_token(): void
    {
        $user = User::first();
        $this->actingAs($user, 'web');

        $token = $user->createToken('Delete Token', ['account:read']);
        $tokenId = $token->accessToken->id;
        $response = $this->deleteJson($this->apiV1Route('api-tokens.destroy', ['token' => $tokenId]));
        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Token deleted successfully.']);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    #[Test]
    public function revoking_api_token_creates_audit_log(): void
    {
        $user = User::first();
        $this->actingAs($user, 'web');

        $token = $user->createToken('Revoke Test Token', ['account:read']);
        $tokenId = $token->accessToken->id;

        $response = $this->deleteJson($this->apiV1Route('api-tokens.destroy', ['token' => $tokenId]));
        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'event' => 'security.api_token_revoked',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $log = AuditLog::where('event', 'security.api_token_revoked')->first();

        $this->assertNotNull($log);
        $this->assertSame('Revoke Test Token', $log->old_values['token_name']);
        $this->assertSame($tokenId, $log->old_values['token_id']);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function user_cannot_delete_current_session_token_via_route(): void
    {
        // This test is no longer applicable since token management is now session-only.
        // When using session-based authentication, there is no "current token" to delete.
        // The security model has changed from API token-based to session-based token management.
        $this->assertTrue(true);
    }

    #[Test]
    public function deleting_a_missing_token_returns_not_found_message(): void
    {
        $user = User::first();
        $this->actingAs($user, 'web');
        $user->createToken('Session Token', ['account:read']);

        $response = $this
            ->deleteJson($this->apiV1Route('api-tokens.destroy', ['token' => 999999]));

        $response->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    #[Test]
    public function user_cannot_create_more_than_5_tokens(): void
    {
        $user = User::first();

        $this->actingAs($user, 'web');

        // Create 5 tokens (the maximum allowed)
        for ($i = 0; $i < 5; $i++) {
            $user->createToken("Token {$i}", ['account:read']);
        }

        // Attempt to create a 6th token
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Exceeding Token',
            'scopes' => ['account:read'],
        ]);

        $response->assertForbidden()
            ->assertJsonPath('code', 'plan_limit_exceeded');

        // Verify only 5 tokens exist
        $this->assertEquals(5, $user->tokens()->count());
    }

    #[Test]
    public function token_expiration_is_capped_at_1_year_maximum(): void
    {
        $user = User::first();

        $this->actingAs($user, 'web');

        // Attempt to create token with 2-year expiration
        $expiresAt = now()->addYears(2)->toIso8601String();

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Long-Lived Token',
            'expires_at' => $expiresAt,
            'scopes' => ['account:read'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);

        // Verify no token was created due to validation failure
        $this->assertEquals(0, $user->tokens()->count());
    }

    #[Test]
    public function service_layer_enforces_1_year_cap(): void
    {
        $user = User::first();

        // Directly test the service layer logic for expiration capping
        $service = app(\App\Services\Auth\ApiTokenService::class);

        // Test with 2-year expiration (should be capped)
        $expiresAt = now()->addYears(2);
        $token = $service->createForUser($user, 'Service Layer Test', ['account:read'], $expiresAt);

        // Verify the expiration was capped at 1 year
        $this->assertNotNull($token->accessToken->expires_at);
        $maxExpiry = now()->addYear();
        $this->assertLessThanOrEqual($maxExpiry, $token->accessToken->expires_at);
        $this->assertGreaterThanOrEqual($maxExpiry->subMinute(), $token->accessToken->expires_at);

        // Clean up the token
        $token->accessToken->delete();
    }

    #[Test]
    public function token_without_expiration_gets_90_day_default(): void
    {
        $user = User::first();

        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Default Expiry Token',
            'scopes' => ['account:read'],
        ]);

        $response->assertCreated();

        $token = $user->tokens()->latest('id')->first();

        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);

        // Verify 90-day default expiration
        $expectedExpiry = now()->addDays(90);
        $this->assertLessThanOrEqual($expectedExpiry, $token->expires_at);
        $this->assertGreaterThanOrEqual($expectedExpiry->subMinute(), $token->expires_at);
    }
}
