<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\SubscriptionHelpers;

class SubscriptionResourceTest extends TestCase
{
    use RefreshDatabase, SubscriptionHelpers;

    private const string SUBSCRIPTIONS_ROUTE = '/api/v1/user/subscriptions';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.monthly', 101);
        config()->set('services.paddle.yearly', 202);

        $this->fakePaddleApi();

        $this->user = User::factory()->create();

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function free_user_receives_correct_subscription_shape(): void
    {
        Project::factory()->count(2)->for($this->user)->create();

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'free')
            ->assertJsonPath('subscription.subscribed', false)
            ->assertJsonPath('subscription.trial.active', false)
            ->assertJsonPath('subscription.grace_period.active', false)
            ->assertJsonPath('subscription.downgraded_to_free', false)
            ->assertJsonPath('subscription.limits.projects.used', 2)
            ->assertJsonPath('subscription.limits.projects.max', 3);
    }

    #[Test]
    public function pro_user_receives_correct_subscription_shape(): void
    {
        $this->createProSubscription($this->user);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'pro')
            ->assertJsonPath('subscription.subscribed', true)
            ->assertJsonPath('subscription.billing_plan', 'monthly')
            ->assertJsonPath('subscription.trial.active', false)
            ->assertJsonPath('subscription.grace_period.active', false)
            ->assertJsonPath('subscription.downgraded_to_free', false)
            ->assertJsonStructure([
                'subscription' => [
                    'billing_plan',
                    'next_payment',
                    'created_at',
                    'receipts',
                ],
            ]);

        $this->assertLimitMaximums($response, [
            'projects' => null,
            'active_tasks_per_project' => null,
            'members_per_project' => null,
            'created_meetings' => null,
            'api_tokens' => null,
        ]);
    }

    #[Test]
    public function grace_period_user_receives_correct_subscription_shape(): void
    {
        $this->createGracePeriodSubscription($this->user);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'pro')
            ->assertJsonPath('subscription.subscribed', true)
            ->assertJsonPath('subscription.grace_period.active', true)
            ->assertJsonPath('subscription.downgraded_to_free', false)
            ->assertJsonStructure([
                'subscription' => [
                    'grace_period' => ['active', 'ends_at'],
                ],
            ]);
    }

    #[Test]
    public function downgraded_user_receives_correct_subscription_shape(): void
    {
        $this->createExpiredSubscription($this->user);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'free')
            ->assertJsonPath('subscription.grace_period.active', false)
            ->assertJsonPath('subscription.downgraded_to_free', true);

        $this->assertLimitMaximums($response, [
            'projects' => 3,
            'active_tasks_per_project' => 10,
            'members_per_project' => 3,
            'created_meetings' => 1,
            'api_tokens' => 1,
        ]);
    }

    #[Test]
    public function trial_user_receives_pro_plan_in_subscription_shape(): void
    {
        $this->createTrialCustomer($this->user, Carbon::now()->addDays(5));

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'pro')
            ->assertJsonPath('subscription.trial.active', true)
            ->assertJsonPath('subscription.downgraded_to_free', false)
            ->assertJsonPath('subscription.limits.projects.max', null);
    }

    private function subscriptionResponse(): TestResponse
    {
        return $this->getJson(self::SUBSCRIPTIONS_ROUTE)
            ->assertOk()
            ->assertJsonStructure([
                'subscription' => [
                    'plan',
                    'subscribed',
                    'trial' => ['active'],
                    'grace_period' => ['active'],
                    'downgraded_to_free',
                    'limits' => [
                        'projects' => ['used', 'max'],
                        'active_tasks_per_project' => ['used', 'max'],
                        'members_per_project' => ['used', 'max'],
                        'created_meetings' => ['used', 'max'],
                        'api_tokens' => ['used', 'max'],
                    ],
                ],
            ]);
    }

    /**
     * @param  array<string, int|null>  $limits
     */
    private function assertLimitMaximums(TestResponse $response, array $limits): void
    {
        foreach ($limits as $limit => $max) {
            $response->assertJsonPath("subscription.limits.{$limit}.max", $max);
        }
    }

    private function fakePaddleApi(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'response' => [[
                'next_payment' => [
                    'amount' => 10.00,
                    'currency' => 'USD',
                    'date' => now()->addMonth()->toDateString(),
                ],
                'user_email' => 'test@example.com',
                'payment_information' => ['payment_method' => 'card'],
            ]],
        ], 200)]);
    }
}
