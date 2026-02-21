<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_admin_user_cannot_access_admin_users_endpoint(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/users')
            ->assertForbidden()
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    #[Test]
    public function admin_user_without_2fa_cannot_access_admin_mutation_endpoint(): void
    {
        $user = User::factory()->create();
        $user->markAsAdmin();

        $target = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/admin/users/{$target->uuid}/grant-admin")
            ->assertForbidden();
    }

    #[Test]
    public function admin_user_with_2fa_can_grant_admin_access(): void
    {
        $actor = User::factory()->create();
        $actor->markAsAdmin();
        $this->enableTwoFactorForUser($actor);

        $target = User::factory()->create();

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/admin/users/{$target->uuid}/grant-admin")
            ->assertOk()
            ->assertJsonPath('user.isAdmin', true);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_admin' => true,
            'admin_granted_by' => $actor->id,
        ]);
    }

    #[Test]
    public function it_validates_user_cannot_be_granted_admin_access_twice(): void
    {
        $actor = User::factory()->create();
        $actor->markAsAdmin();
        $this->enableTwoFactorForUser($actor);

        $target = User::factory()->create();
        $target->markAsAdmin($actor);

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/admin/users/{$target->uuid}/grant-admin")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    #[Test]
    public function admin_user_with_2fa_can_revoke_admin_access(): void
    {
        $actor = User::factory()->create();
        $actor->markAsAdmin();
        $this->enableTwoFactorForUser($actor);

        $target = User::factory()->create([
            'remember_token' => 'known-token-value',
        ]);
        $target->markAsAdmin($actor);
        $target->createToken('admin-device-token');

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/admin/users/{$target->uuid}/revoke-admin")
            ->assertOk()
            ->assertJsonPath('user.isAdmin', false);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_admin' => false,
            'admin_granted_by' => null,
        ]);
    }

    #[Test]
    public function it_validates_non_admin_user_cannot_be_revoked_again(): void
    {
        $actor = User::factory()->create();
        $actor->markAsAdmin();
        $this->enableTwoFactorForUser($actor);

        $target = User::factory()->create();

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/admin/users/{$target->uuid}/revoke-admin")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    #[Test]
    public function revoking_admin_access_revokes_tokens_and_rotates_remember_token(): void
    {
        $actor = User::factory()->create();
        $actor->markAsAdmin();

        $target = User::factory()->create([
            'remember_token' => 'known-token-value',
        ]);

        $target->markAsAdmin($actor);
        $target->createToken('admin-device-token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);

        $target->revokeAdminAccess($actor);
        $target->refresh();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);

        $this->assertNotSame('known-token-value', $target->remember_token);
        $this->assertNotNull($target->remember_token);
    }

    private function enableTwoFactorForUser(User $user): void
    {
        $twoFactor = $user->createTwoFactorAuth();

        $twoFactor->forceFill([
            'label' => "DayWright:{$user->email}",
        ])->save();

        $user->enableTwoFactorAuth();
    }
}
