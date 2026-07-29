<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Services\Admin\AdminAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\EnablesUserTwoFactor;

class UsersTest extends TestCase
{
    use EnablesUserTwoFactor;
    use RefreshDatabase;

    #[Test]
    public function non_admin_user_cannot_access_admin_users_endpoint(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $this->getJson($this->apiV1AdminRoute('users.index'))
            ->assertForbidden()
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    #[Test]
    public function admin_user_without_2fa_cannot_access_admin_mutation_endpoint(): void
    {
        $user = $this->createAdminUser();

        $target = $this->createUser();

        Sanctum::actingAs($user);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => true,
        ])
            ->assertForbidden();
    }

    #[Test]
    public function admin_user_with_2fa_can_grant_admin_access(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        $target = $this->createUser();

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.admin_granted_at', $target->fresh()->admin_granted_at?->setTimezone('UTC')->toIso8601String());

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_admin' => true,
            'admin_granted_by' => $actor->id,
        ]);
    }

    #[Test]
    public function granting_admin_access_creates_audit_log(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        $target = $this->createUser();

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => true,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'api_token',
            'actor_id' => $actor->id,
            'event' => 'security.role_updated',
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
        ]);

        $log = AuditLog::where('event', 'security.role_updated')->first();

        $this->assertNotNull($log);
        $this->assertFalse($log->old_values['is_admin']);
        $this->assertTrue($log->new_values['is_admin']);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function it_validates_user_cannot_be_granted_admin_access_twice(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        $target = $this->createUser();
        (new AdminAccessService)->grantAdminAccess($target, $actor);

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    #[Test]
    public function admin_role_update_requires_boolean_role_state(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        $target = $this->createUser();

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_admin']);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => 'maybe',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_admin']);
    }

    #[Test]
    public function admin_user_with_2fa_can_revoke_admin_access(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        $target = $this->createUser([
            'remember_token' => 'known-token-value',
        ]);
        (new AdminAccessService)->grantAdminAccess($target, $actor);
        $target->createToken('admin-device-token');

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_admin', false)
            ->assertJsonPath('data.admin_revoked_at', $target->fresh()->admin_revoked_at?->setTimezone('UTC')->toIso8601String());

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_admin' => false,
            'admin_granted_by' => $actor->id,
            'admin_revoked_by' => $actor->id,
        ]);

        $target->refresh();
        $this->assertNotNull($target->admin_granted_at);
        $this->assertNotNull($target->admin_revoked_at);
    }

    #[Test]
    public function revoking_admin_access_creates_audit_log(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        $target = $this->createUser();
        (new AdminAccessService)->grantAdminAccess($target, $actor);

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => false,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'api_token',
            'actor_id' => $actor->id,
            'event' => 'security.role_updated',
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
        ]);

        $log = AuditLog::where('event', 'security.role_updated')->latest()->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->old_values['is_admin']);
        $this->assertFalse($log->new_values['is_admin']);
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function it_validates_non_admin_user_cannot_be_revoked_again(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        $target = $this->createUser();

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $target]), [
            'is_admin' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    #[Test]
    public function cannot_revoke_last_admin_account(): void
    {
        $actor = $this->createAdminUser();
        $this->enableTwoFactorForUser($actor);

        Sanctum::actingAs($actor);

        $this->patchJson($this->apiV1AdminRoute('users.role.update', ['user' => $actor]), [
            'is_admin' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    #[Test]
    public function users_index_returns_active_member_projects_count(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        $target = $this->createUser();

        $activeProjectOne = $this->createProject();
        $activeProjectOne->members()->attach($target->id, ['active' => true]);

        $activeProjectTwo = $this->createProject();
        $activeProjectTwo->members()->attach($target->id, ['active' => true]);

        $inactiveProject = $this->createProject();
        $inactiveProject->members()->attach($target->id, ['active' => false]);

        Sanctum::actingAs($admin);

        $response = $this->getJson($this->apiV1AdminRoute('users.index'))->assertOk();

        $payload = $response->json('data');
        $this->assertIsArray($payload);

        /** @var array<int, array<string, mixed>> $payload */
        $targetPayload = collect($payload)->firstWhere('uuid', $target->uuid);

        $this->assertNotNull($targetPayload);

        /** @var array<string, mixed> $targetPayload */
        $this->assertSame(2, $targetPayload['projects_member']);
    }

    #[Test]
    public function users_index_can_filter_by_search_and_per_page(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        $matchingUser = $this->createUser(['name' => 'Searchable Admin User']);
        $this->createUser(['name' => 'Other User']);

        Sanctum::actingAs($admin);

        $response = $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'filter' => ['search' => 'Searchable'],
            'per_page' => 1,
        ]))->assertOk();

        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($matchingUser->uuid, $data[0]['uuid']);
        $this->assertSame(1, $response->json('meta.per_page'));
    }

    #[Test]
    public function users_index_search_treats_sql_wildcards_as_literals(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        $literalUser = $this->createUser(['name' => 'Literal% User']);
        $this->createUser(['name' => 'LiteralX User']);

        Sanctum::actingAs($admin);

        $response = $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'filter' => ['search' => 'Literal%'],
        ]))->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($literalUser->uuid, $response->json('data.0.uuid'));
    }

    #[Test]
    public function users_index_rejects_legacy_top_level_search_alias(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        $this->createUser(['name' => 'Alias Search User']);
        $this->createUser(['name' => 'Other User']);

        Sanctum::actingAs($admin);

        $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'search' => 'Alias Search',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    #[Test]
    public function users_index_validates_sort_parameter(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        Sanctum::actingAs($admin);

        $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'sort' => 'invalid',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    #[Test]
    public function users_index_rejects_unknown_filter_keys(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        Sanctum::actingAs($admin);

        $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'filter' => ['role' => 'admin'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    #[Test]
    public function users_index_rejects_unsupported_top_level_query_parameters(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        Sanctum::actingAs($admin);

        $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['random']);
    }

    #[Test]
    public function users_index_rejects_unsupported_spatie_package_parameters(): void
    {
        $admin = $this->createAdminUser();
        $this->enableTwoFactorForUser($admin);

        Sanctum::actingAs($admin);

        $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'include' => 'subscriptions',
            'fields' => ['users' => 'id,name'],
            'append' => 'foo',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include', 'fields', 'append']);
    }

    #[Test]
    public function users_index_can_sort_by_name(): void
    {
        $admin = $this->createAdminUser(['name' => 'ZZZ Sorting Admin']);
        $this->enableTwoFactorForUser($admin);

        $alphaUser = $this->createUser(['name' => 'Alpha Admin User']);
        $zuluUser = $this->createUser(['name' => 'Zulu Admin User']);

        Sanctum::actingAs($admin);

        $response = $this->getJson($this->apiV1AdminRoute('users.index', query: [
            'sort' => 'name',
        ]))->assertOk();

        $this->assertSame($alphaUser->uuid, $response->json('data.0.uuid'));
        $this->assertContains($zuluUser->uuid, collect($response->json('data'))->pluck('uuid')->all());
    }

    #[Test]
    public function users_index_defaults_to_newest_first_when_sort_is_omitted(): void
    {
        $admin = $this->createAdminUser([
            'created_at' => now()->subDays(10),
        ]);
        $this->enableTwoFactorForUser($admin);

        $oldUser = $this->createUser([
            'name' => 'Old User',
            'created_at' => now()->subDays(3),
        ]);
        $newUser = $this->createUser([
            'name' => 'New User',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson($this->apiV1AdminRoute('users.index'))
            ->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid')->take(2)->all();

        $this->assertSame([$newUser->uuid, $oldUser->uuid], $uuids);
    }

    #[Test]
    public function revoking_admin_access_revokes_tokens_and_rotates_remember_token(): void
    {
        $actor = $this->createAdminUser();

        $target = $this->createUser([
            'remember_token' => 'known-token-value',
        ]);

        (new AdminAccessService)->grantAdminAccess($target, $actor);
        $target->createToken('admin-device-token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);

        (new AdminAccessService)->revokeAdminAccess($target, $actor);
        $target->refresh();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);

        $this->assertNotSame('known-token-value', $target->remember_token);
        $this->assertNotNull($target->remember_token);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAdminUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->admin()->create($attributes);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProject(array $attributes = []): Project
    {
        /** @var Project $project */
        $project = Project::factory()->create($attributes);

        return $project;
    }
}
