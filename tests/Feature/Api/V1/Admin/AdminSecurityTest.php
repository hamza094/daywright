<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ApiScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_key_is_blocked_from_admin_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $token = $admin->createToken('Admin API Key', [ApiScope::AccountRead->value]);

        // Use only API token auth (no web session)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->getJson('/api/v1/admin/projects');

        $response->assertForbidden()
            ->assertJsonPath('message', 'This operation is strictly reserved for the web dashboard. Please use session-based authentication.');
    }

    #[Test]
    public function session_auth_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin, 'web');

        $response = $this->getJson('/api/v1/admin/projects');

        $response->assertOk();
    }
}
