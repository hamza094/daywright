<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Controllers\Paddle;

use App\Http\Middleware\CheckSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\InteractsWithPaddle;

class SubscriptionControllerTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
        ]);

        /** @var User $user */
        Sanctum::actingAs($user);

        $this->fakeSubscription();
    }

    #[Test]
    public function it_creates_a_paylink_for_subscription(): void
    {
        $plan = 'monthly';
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('subscriptions.store'), [
            'plan' => $plan,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.paylink', 'https://fake-paylink-url.com');
    }

    #[Test]
    public function it_swaps_a_subscription_plan(): void
    {
        $this->withoutMiddleware(CheckSubscription::class);

        $plan = 'yearly';

        $response = $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('subscription.swap'), [
            'plan' => $plan,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.subscribed', false);
    }

    #[Test]
    public function it_cancels_a_subscription(): void
    {
        $this->withoutMiddleware(CheckSubscription::class);

        $plan = 'yearly';
        $response = $this->deleteJson($this->apiV1Route('subscription.cancel'), [
            'plan' => $plan,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.subscribed', false);
    }

    #[Test]
    public function it_denies_access_for_non_subscribed_users(): void
    {
        $plan = 'monthly';
        $response = $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('subscription.swap'), [
            'plan' => $plan,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. An active subscription is required to perform this action.',
                'code' => 'subscription_required',
                'errors' => [],
                'meta' => [
                    'upgrade_required' => true,
                ],
            ]);
    }

    #[Test]
    public function it_fails_validation_for_invalid_plan(): void
    {
        $invalidPlan = 'weekly';
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('subscriptions.store'), [
            'plan' => $invalidPlan,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }
}
