<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
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
        $response = $this->getJson('/api/v1/api-tokens');
        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Test Token']);
    }

    #[Test]
    public function user_can_create_a_token(): void
    {
        $user = User::first();

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $response = $this->postJson('/api/v1/api-tokens', [
            'name' => 'My API Token',
        ]);
        $response->assertCreated();
        $response->assertJsonFragment(['message' => 'Token created successfully.']);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'My API Token',
            'tokenable_id' => $user->id,
        ]);
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
        $response = $this->deleteJson('/api/v1/api-tokens/'.$tokenId);
        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Token deleted.']);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    #[Test]
    public function session_authenticated_user_can_delete_a_stored_personal_access_token(): void
    {
        $user = User::first();
        $token = $user->createToken('Delete Token', ['*']);
        $tokenId = $token->accessToken->id;

        $response = $this
            ->actingAs($user, 'web')
            ->deleteJson('/api/v1/api-tokens/'.$tokenId);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Token deleted.']);

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
            ->deleteJson("/api/v1/api-tokens/{$tokenModel->id}");

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Cannot delete the current session token via this route.',
            ]);
    }
}
