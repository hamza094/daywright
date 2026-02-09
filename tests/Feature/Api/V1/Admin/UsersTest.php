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

        Sanctum::actingAs($user);

        $response = $this->getJson('api/v1/admin/users');

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.last_active');
    }
}
