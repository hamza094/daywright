<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class RouteVersionRegistrationTest extends TestCase
{
    public function test_v1_route_registration_remains_stable(): void
    {
        $this->assertSame('/api/v1/login', route('api.v1.auth.login', absolute: false));
        $this->assertSame('/api/v1/session/login', route('api.v1.session.login', absolute: false));
        $this->assertSame('/api/v1/auth/redirect/github', route('api.v1.oauth.redirect', ['provider' => 'github'], false));
        $this->assertSame('/api/v1/twofactor/login-confirm', route('api.v1.twofactor.login-confirm', absolute: false));
        $this->assertSame('/api/v1/users/me', route('api.v1.users.me.show', absolute: false));
        $this->assertSame('/api/v1/users/me/invitations', route('api.v1.users.me.invitations.index', absolute: false));
        $this->assertSame('/api/v1/users/me/zoom-token', route('api.v1.users.me.zoom-token', absolute: false));
        $this->assertSame('/api/v1/users/me/zoom-jwt-token', route('api.v1.users.me.zoom-jwt-token', absolute: false));
        $this->assertSame('/api/v1/users/me/subscription', route('api.v1.users.me.subscription.show', absolute: false));
        $this->assertSame('/api/v1/users/me/subscription', route('api.v1.users.me.subscription.store', absolute: false));
        $this->assertSame('/api/v1/users/me/subscription', route('api.v1.users.me.subscription.update', absolute: false));
        $this->assertSame('/api/v1/users/me/subscription', route('api.v1.users.me.subscription.destroy', absolute: false));
        $this->assertSame('/api/v1/users', route('api.v1.users.index', absolute: false));
        $this->assertSame(
            '/api/v1/projects/project-slug/tasks/123/members/search',
            route('api.v1.task.members.search', ['project' => 'project-slug', 'task' => 123], false),
        );
        $this->assertSame('/api/v1/admin/users', route('api.v1.admin.users.index', absolute: false));
    }
}
