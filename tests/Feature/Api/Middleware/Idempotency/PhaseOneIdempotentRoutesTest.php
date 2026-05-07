<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

class PhaseOneIdempotentRoutesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function phase_one_hot_path_routes_are_protected_by_user_scoped_idempotency_middleware(): void
    {
        $userScopedIdempotent = Idempotent::using(scope: IdempotencyScope::User);

        foreach ([
            'api.v1.api-tokens.store',
            'api.v1.subscriptions.store',
            'api.v1.subscription.swap',
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
    public function subscription_cancel_remains_a_delete_route_without_idempotency_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('api.v1.subscription.cancel');

        $this->assertNotNull($route);
        $this->assertContains('DELETE', $route->methods());
        $this->assertNotContains(Idempotent::using(scope: IdempotencyScope::User), $route->gatherMiddleware());
    }

    #[Test]
    public function protected_token_creation_requires_an_idempotency_key(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'My API Token',
        ])->assertBadRequest()
            ->assertJsonPath('message', 'Missing required header: Idempotency-Key');
    }

    #[Test]
    public function protected_token_creation_replays_the_first_response_for_the_same_key(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = ['name' => 'My API Token'];
        $headers = $this->idempotencyHeaders('phase-one-token-create');

        $firstResponse = $this->withHeaders($headers)->postJson($this->apiV1Route('api-tokens.store'), $payload);
        $secondResponse = $this->withHeaders($headers)->postJson($this->apiV1Route('api-tokens.store'), $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();
        $this->assertSame($firstResponse->json(), $secondResponse->json());
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
