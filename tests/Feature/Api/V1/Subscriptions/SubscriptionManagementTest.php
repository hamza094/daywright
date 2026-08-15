<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Subscriptions;

use App\Http\Middleware\CheckSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Paddle\Subscription as PaddleSubscription;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\InteractsWithPaddle;

class SubscriptionManagementTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paddle.monthly' => '123456']);
        config(['services.paddle.yearly' => '789012']);

        $user = User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
        ]);

        /** @var User $user */
        $this->actingAs($user, 'web');

        $this->fakeSubscription();
    }

    #[Test]
    public function it_creates_a_paylink_for_subscription(): void
    {
        $plan = 'monthly';
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('users.me.subscription.store'), [
            'plan' => $plan,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.paylink', 'https://fake-paylink-url.com');
    }

    #[Test]
    public function it_swaps_a_subscription_plan(): void
    {
        $this->withoutMiddleware(CheckSubscription::class);

        $user = auth()->user();
        $this->fakeSubscription()->setState($user, 'active');

        $plan = 'yearly';

        $response = $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('users.me.subscription.update'), [
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
        $response = $this->deleteJson($this->apiV1Route('users.me.subscription.destroy'), [
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
        $response = $this->withHeaders($this->idempotencyHeaders())->patchJson($this->apiV1Route('users.me.subscription.update'), [
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
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('users.me.subscription.store'), [
            'plan' => $invalidPlan,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    #[Test]
    public function it_returns_billing_status_for_active_subscription(): void
    {
        $user = auth()->user();
        $this->createSubscription($user, 'active');

        $response = $this->getJson($this->apiV1Route('users.me.subscription.show'));

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.billing_status', 'active');
    }

    #[Test]
    public function it_returns_billing_status_for_trialing_subscription(): void
    {
        $user = auth()->user();
        $this->createSubscription($user, 'trialing');

        $response = $this->getJson($this->apiV1Route('users.me.subscription.show'));

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.billing_status', 'trialing');
    }

    #[Test]
    public function it_returns_subscribed_false_for_past_due_subscription(): void
    {
        $user = auth()->user();
        $this->createSubscription($user, 'past_due');

        $response = $this->getJson($this->apiV1Route('users.me.subscription.show'));

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribed', false)
            ->assertJsonPath('data.billing_status', 'past_due');
    }

    #[Test]
    public function it_returns_subscribed_false_for_paused_subscription(): void
    {
        $user = auth()->user();
        $this->createSubscription($user, 'paused');

        $response = $this->getJson($this->apiV1Route('users.me.subscription.show'));

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribed', false)
            ->assertJsonPath('data.billing_status', 'paused');
    }

    #[Test]
    public function it_returns_subscribed_false_for_canceled_subscription(): void
    {
        $user = auth()->user();
        $this->createSubscription($user, 'canceled');

        $response = $this->getJson($this->apiV1Route('users.me.subscription.show'));

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribed', false)
            ->assertJsonPath('data.billing_status', 'canceled');
    }

    #[Test]
    public function it_returns_subscribed_false_for_canceled_grace_subscription(): void
    {
        $user = auth()->user();
        $subscription = $this->createSubscription($user, 'canceled');
        $subscription->ends_at = now()->addDays(7);
        $subscription->save();

        $response = $this->getJson($this->apiV1Route('users.me.subscription.show'));

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribed', false)
            ->assertJsonPath('data.billing_status', 'canceled');
    }

    #[Test]
    public function it_returns_null_billing_status_when_no_subscription(): void
    {
        $response = $this->getJson($this->apiV1Route('users.me.subscription.show'));

        $response->assertStatus(200)
            ->assertJsonPath('data.subscribed', false)
            ->assertJsonPath('data.billing_status', null);
    }

    #[Test]
    public function cancel_endpoint_is_idempotent(): void
    {
        $this->withoutMiddleware(CheckSubscription::class);

        $plan = 'yearly';
        $idempotencyKey = (string) Str::uuid();

        $response1 = $this->withHeaders($this->idempotencyHeaders($idempotencyKey))->deleteJson($this->apiV1Route('users.me.subscription.destroy'), [
            'plan' => $plan,
        ]);

        $response1->assertStatus(200);

        $response2 = $this->withHeaders($this->idempotencyHeaders($idempotencyKey))->deleteJson($this->apiV1Route('users.me.subscription.destroy'), [
            'plan' => $plan,
        ]);

        $response2->assertStatus(200);
    }

    #[Test]
    public function it_throws_exception_for_invalid_plan_config(): void
    {
        config(['services.paddle.monthly' => null]);

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1Route('users.me.subscription.store'), [
            'plan' => 'monthly',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'subscription_conflict');
    }

    private function createSubscription(User $user, string $status): PaddleSubscription
    {
        $subscription = new PaddleSubscription([
            'billable_id' => $user->getKey(),
            'billable_type' => $user->getMorphClass(),
            'name' => $user->subscriptionName(),
            'paddle_id' => fake()->numberBetween(100000, 999999),
            'paddle_status' => $status,
            'paddle_plan' => fake()->numberBetween(100000, 999999),
            'quantity' => 1,
        ]);

        $subscription->save();

        return $subscription;
    }
}
