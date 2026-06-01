<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Contracts\Routes;

use Tests\TestCase;

class RouteVersionRegistrationTest extends TestCase
{
    public function test_v1_route_registration_remains_stable_for_core_entry_points(): void
    {
        $this->assertRoutePath('api.v1.auth.login', '/api/v1/login');
        $this->assertRoutePath('api.v1.session.login', '/api/v1/session/login');
        $this->assertRoutePath('api.v1.oauth.redirect', '/api/v1/auth/redirect/github', ['provider' => 'github']);
        $this->assertRoutePath('api.v1.twofactor.login-confirm', '/api/v1/twofactor/login-confirm');
        $this->assertRoutePath('api.v1.users.me.show', '/api/v1/users/me');
        $this->assertRoutePath('api.v1.users.me.subscription.show', '/api/v1/users/me/subscription');
        $this->assertRoutePath('api.v1.users.me.subscription.store', '/api/v1/users/me/subscription');
        $this->assertRoutePath('api.v1.users.me.subscription.update', '/api/v1/users/me/subscription');
        $this->assertRoutePath('api.v1.users.me.subscription.destroy', '/api/v1/users/me/subscription');
        $this->assertRoutePath(
            'api.v1.task.members.search',
            '/api/v1/projects/project-slug/tasks/123/members/search',
            ['project' => 'project-slug', 'task' => 123],
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function assertRoutePath(string $routeName, string $expectedPath, array $parameters = []): void
    {
        $this->assertSame($expectedPath, route($routeName, $parameters, false));
    }
}
