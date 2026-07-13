<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class BroadcastAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function broadcasting_auth_route_uses_sanctum_middleware(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/api/broadcasting/auth', 'GET'));
        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth:sanctum', $middleware);
    }

    /** @test */
    public function unauthenticated_users_cannot_authorize_private_channels(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.app_id', 'test-app-id');
        config()->set('broadcasting.connections.pusher.key', 'test-key');
        config()->set('broadcasting.connections.pusher.secret', 'test-secret');

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.00000000-0000-0000-0000-000000000001',
            ]);

        $response->assertUnauthorized();
    }
}
