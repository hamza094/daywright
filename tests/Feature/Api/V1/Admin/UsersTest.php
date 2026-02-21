<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_users_response_excludes_last_active(): void
    {
        $user = User::factory()->create();
        $user->markAsAdmin();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.last_active');
    }

    /** @test */
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

    /** @test */
    public function admin_user_without_2fa_cannot_access_admin_mutation_endpoint(): void
    {
        $user = User::factory()->create();
        $user->markAsAdmin();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/admin/tasks/bulk-delete', ['tasks' => []])
            ->assertForbidden();
    }

    /** @test */
    public function it_updates_admin_access_fields_when_granted_and_revoked(): void
    {
        $actor = User::factory()->create();
        $actor->markAsAdmin();

        $target = User::factory()->create();
        $target->markAsAdmin($actor);
        $target->refresh();

        $this->assertTrue($target->is_admin);
        $this->assertNotNull($target->admin_granted_at);
        $this->assertSame($actor->id, $target->admin_granted_by);

        $target->revokeAdminAccess($actor);
        $target->refresh();

        $this->assertFalse($target->is_admin);
        $this->assertNull($target->admin_granted_at);
        $this->assertNull($target->admin_granted_by);
    }

    /** @test */
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
}
