<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

class IdempotentRoutesRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function phase_one_hot_path_routes_are_protected_by_user_scoped_idempotency_middleware(): void
    {
        $userScopedIdempotent = Idempotent::using(scope: IdempotencyScope::User);

        foreach ([
            'api.v1.api-tokens.store',
            'api.v1.users.me.subscription.store',
            'api.v1.users.me.subscription.update',
            'api.v1.projects.messages.store',
            'api.v1.send.invitation',
            'api.v1.accept.invitation',
            'api.v1.reject.invitation',
            'api.v1.task.assign',
            'api.v1.task.unassign',
            'api.v1.meetings.store',
            'api.v1.meetings.update',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] is not registered.");
            $this->assertContains(
                $userScopedIdempotent,
                $route->gatherMiddleware(),
                "Route [{$routeName}] is missing the expected idempotency middleware.",
            );
        }
    }

    #[Test]
    public function subscription_cancel_route_has_user_scoped_idempotency_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('api.v1.users.me.subscription.destroy');

        $this->assertNotNull($route);
        $this->assertContains('DELETE', $route->methods());
        $this->assertContains(Idempotent::using(scope: IdempotencyScope::User), $route->gatherMiddleware());
    }

    #[Test]
    public function protected_token_creation_requires_an_idempotency_key(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'My API Token',
        ])->assertBadRequest()
            ->assertJsonPath('message', 'Missing required header: Idempotency-Key');
    }
}
