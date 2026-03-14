<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Controllers\Paddle;

use App\Http\Middleware\CheckSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Override;
use Tests\TestCase;
use Tests\Traits\InteractsWithPaddle;

class SubscriptionTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $user = User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
        ]);

        Sanctum::actingAs($user);

        // Use the fake subscription service
        $this->fakeSubscription();
    }

    /** @test */
    public function it_creates_a_paylink_for_subscription(): void
    {
        $plan = 'monthly';
        $response = $this->getJson('/api/v1/user/subscribe/'.$plan);

        $response->assertStatus(200)
            ->assertJson([
                'paylink' => 'https://fake-paylink-url.com',
            ]);
    }

    /** @test */
    public function it_swaps_a_subscription_plan(): void
    {
        $this->withoutMiddleware(CheckSubscription::class);

        $plan = 'yearly';

        $response = $this->getJson('/api/v1/user/subscription/swap/'.$plan);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Your subscription has been successfully updated to the yearly plan (fake).',
            ]);
    }

    /** @test */
    public function it_cancels_a_subscription(): void
    {
        $this->withoutMiddleware(CheckSubscription::class);

        $plan = 'yearly';
        $response = $this->getJson('/api/v1/user/subscription/'.$plan.'/cancel');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Your subscription has been canceled successfully (fake).',
            ]);
    }

    /** @test */
    public function it_denies_access_for_non_subscribed_users(): void
    {
        $plan = 'monthly';
        $response = $this->getJson('/api/v1/user/subscription/swap/'.$plan);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. An active subscription is required to perform this action.',
                'error_type' => 'subscription_required',
                'upgrade_required' => true,
            ]);
    }

    /** @test */
    public function it_fails_validation_for_invalid_plan(): void
    {
        $invalidPlan = 'weekly';
        $response = $this->getJson('/api/v1/user/subscribe/'.$invalidPlan);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }
}
