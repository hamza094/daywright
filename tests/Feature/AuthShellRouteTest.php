<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthShellRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_route_does_not_enable_session_bootstrap_for_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('data-bootstrap-session="true"', false);
    }

    #[Test]
    public function register_route_does_not_enable_session_bootstrap_for_guests(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertDontSee('data-bootstrap-session="true"', false);
    }

    #[Test]
    public function authenticated_user_is_redirected_away_from_login_route(): void
    {
        $this->actingAs(
            \App\Models\User::factory()->create(),
            'web',
        );

        $this->get('/login')
            ->assertRedirect(RouteServiceProvider::HOME);
    }

    #[Test]
    public function dashboard_shell_keeps_session_bootstrap_enabled(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-bootstrap-session="true"', false);
    }
}
