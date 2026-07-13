<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_includes_bootstrap_session_attribute_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('data-bootstrap-session="true"', false);
    }

    /** @test */
    public function it_does_not_include_bootstrap_session_attribute_for_guest_users(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('data-bootstrap-session="true"', false);
    }

    /** @test */
    public function it_does_not_include_bootstrap_session_attribute_on_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('data-bootstrap-session="true"', false);
    }
}
