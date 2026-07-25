<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Users;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
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

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $user->createToken('Test Token', ['*']);
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

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'My API Token',
        ]);
        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.token'));
        $response->assertJsonPath('data.token_resource.expires_at', null);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'My API Token',
            'tokenable_id' => $user->id,
        ]);

        $token = $user->tokens()->latest('id')->first();

        $this->assertNotNull($token);
        $this->assertNull($token->expires_at);
    }

    #[Test]
    public function user_can_create_a_token_with_iso_expiration(): void
    {
        $user = User::first();

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $expiresAt = '2026-05-20T15:30:00+02:00';
        $expectedExpiration = CarbonImmutable::parse($expiresAt)->setTimezone('UTC')->toIso8601String();

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Expiring API Token',
            'expires_at' => $expiresAt,
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

        Sanctum::actingAs(
            $user,
            ['*'],
        );

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

        Sanctum::actingAs(
            $user,
        );

        $token = $user->createToken('Delete Token', ['*']);
        $tokenId = $token->accessToken->id;
        $response = $this->deleteJson($this->apiV1Route('api-tokens.destroy', ['token' => $tokenId]));
        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Token deleted successfully.']);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    #[Test]
    public function user_cannot_delete_current_session_token_via_route(): void
    {
        $user = User::first();
        $tokenResult = $user->createToken('Session Token', ['*']);
        $plainText = $tokenResult->plainTextToken;
        $tokenModel = $tokenResult->accessToken;

        $response = $this
            ->withToken($plainText)
            ->deleteJson($this->apiV1Route('api-tokens.destroy', ['token' => $tokenModel->id]));

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Cannot delete the current session token via this route.',
            ]);
    }

    #[Test]
    public function deleting_a_missing_token_returns_not_found_message(): void
    {
        $user = User::first();
        $tokenResult = $user->createToken('Session Token', ['*']);

        $response = $this
            ->withToken($tokenResult->plainTextToken)
            ->deleteJson($this->apiV1Route('api-tokens.destroy', ['token' => 999999]));

        $response->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }
}
