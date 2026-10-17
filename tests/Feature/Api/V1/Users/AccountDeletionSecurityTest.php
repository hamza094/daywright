<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AccountDeletionSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function third_party_api_token_cannot_delete_account(): void
    {
        // Create a user with an API token (any scope, since tokenAbility middleware is removed)
        $tokenUser = User::factory()->create();
        $token = $tokenUser->createToken('test-token', ['team:read'])->plainTextToken;

        // Try to delete their own account using the bearer token
        $response = $this->withToken($token)
            ->deleteJson(route('api.v1.users.destroy', ['user' => $tokenUser]));

        // Should be forbidden because firstParty.auth is required
        $response->assertForbidden();

        // Verify user's account was not deleted
        $this->assertDatabaseHas('users', [
            'id' => $tokenUser->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function third_party_api_token_cannot_force_delete_account(): void
    {
        // Create a user with an API token (any scope, since tokenAbility middleware is removed)
        $tokenUser = User::factory()->create();
        $token = $tokenUser->createToken('test-token', ['team:read'])->plainTextToken;

        // Try to force delete the user using the bearer token (without soft delete first)
        $response = $this->withToken($token)
            ->deleteJson(route('api.v1.users.forceDestroy', ['user' => $tokenUser]));

        // Should be forbidden because firstParty.auth is required
        $response->assertForbidden();

        // Verify user's account still exists
        $this->assertDatabaseHas('users', [
            'id' => $tokenUser->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function session_auth_can_delete_account(): void
    {
        // Use the real web guard so the first-party boundary is exercised.
        $sessionUser = User::factory()->create();
        $this->actingAs($sessionUser, 'web');

        // Try to delete their own account using session auth
        $response = $this->deleteJson(route('api.v1.users.destroy', ['user' => $sessionUser]));

        // Should succeed because first-party session is allowed
        $response->assertOk();

        // Verify user's account was soft-deleted
        $this->assertSoftDeleted('users', ['id' => $sessionUser->id]);
    }

    #[Test]
    public function session_auth_can_force_delete_account(): void
    {
        // Use the real web guard so the first-party boundary is exercised.
        $sessionUser = User::factory()->create();
        $this->actingAs($sessionUser, 'web');

        // First soft delete the user
        $this->deleteJson(route('api.v1.users.destroy', ['user' => $sessionUser]))->assertOk();

        // Re-authenticate after soft delete (session still valid)
        $this->actingAs($sessionUser, 'web');

        // Try to force delete their own account using session auth
        $response = $this->deleteJson(route('api.v1.users.forceDestroy', ['user' => $sessionUser]));

        // Should succeed because first-party session is allowed
        $response->assertOk();

        // Verify user's account was permanently deleted
        $this->assertDatabaseMissing('users', ['id' => $sessionUser->id]);
    }

    #[Test]
    public function application_issued_wildcard_token_can_delete_account(): void
    {
        // Wildcard tokens represent application-issued first-party credentials.
        $mobileUser = User::factory()->create();
        $token = $mobileUser->createToken('mobile-app-token', ['*'])->plainTextToken;

        // Try to delete their own account using the mobile app token
        $response = $this->withToken($token)
            ->deleteJson(route('api.v1.users.destroy', ['user' => $mobileUser]));

        // Should succeed because application-issued wildcard tokens are allowed.
        $response->assertOk();

        // Verify user's account was soft-deleted
        $this->assertSoftDeleted('users', ['id' => $mobileUser->id]);
    }

    #[Test]
    public function application_issued_wildcard_token_can_force_delete_account(): void
    {
        // Wildcard tokens represent application-issued first-party credentials.
        $mobileUser = User::factory()->create();
        $token = $mobileUser->createToken('mobile-app-token', ['*'])->plainTextToken;

        // First soft delete the user
        $this->withToken($token)
            ->deleteJson(route('api.v1.users.destroy', ['user' => $mobileUser]))->assertOk();

        // Try to force delete their own account using the mobile app token
        $response = $this->withToken($token)
            ->deleteJson(route('api.v1.users.forceDestroy', ['user' => $mobileUser]));

        // Should succeed because application-issued wildcard tokens are allowed.
        $response->assertOk();

        // Verify user's account was permanently deleted
        $this->assertDatabaseMissing('users', ['id' => $mobileUser->id]);
    }
}
