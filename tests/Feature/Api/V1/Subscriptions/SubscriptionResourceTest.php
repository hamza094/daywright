<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Subscriptions;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;

class SubscriptionResourceTest extends TestCase
{
    use RefreshDatabase, SubscriptionHelpers;

    private const string SUBSCRIPTIONS_ROUTE = '/api/v1/user/subscriptions';

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.monthly', 101);
        config()->set('services.paddle.yearly', 202);
        config()->set('services.paddle.prices.monthly', 12);
        config()->set('services.paddle.prices.yearly', 100);
        config()->set('services.paddle.prices.currency', 'USD');

        // SubscriptionHelpers fakes the Paddle vendor API calls used by the resource.
        $this->fakePaddleVendorApiResponses();

        /** @var User $user */
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->user = $user;
    }

    #[Test]
    public function free_user_receives_correct_subscription_shape(): void
    {
        Project::factory()->count(2)->for($this->user)->create();

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'free')
            ->assertJsonPath('subscription.entitled', false)
            ->assertJsonPath('subscription.subscribed', false)
            ->assertJsonPath('subscription.billing_plan', null)
            ->assertJsonPath('subscription.next_payment', null)
            ->assertJsonPath('subscription.created_at', null)
            ->assertJsonPath('subscription.receipts', [])
            ->assertJsonPath('subscription.trial.active', false)
            ->assertJsonPath('subscription.trial.ends_at', null)
            ->assertJsonPath('subscription.grace_period.active', false)
            ->assertJsonPath('subscription.grace_period.ends_at', null)
            ->assertJsonPath('subscription.available_plans.0.price', 12)
            ->assertJsonPath('subscription.available_plans.1.price', 100)
            ->assertJsonMissingPath('subscription.limits.active_tasks_per_project')
            ->assertJsonMissingPath('subscription.limits.members_per_project')
            ->assertJsonPath('subscription.limits.projects.used', 2)
            ->assertJsonPath('subscription.limits.projects.max', 3);
    }

    #[Test]
    public function pro_user_receives_correct_subscription_shape(): void
    {
        // SubscriptionHelpers seeds the active subscription and its receipt.
        $subscription = $this->createProSubscription($this->user);
        $this->createReceipt($this->user, $subscription);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'pro')
            ->assertJsonPath('subscription.entitled', true)
            ->assertJsonPath('subscription.subscribed', true)
            ->assertJsonPath('subscription.billing_plan', 'monthly')
            ->assertJsonPath('subscription.trial.active', false)
            ->assertJsonPath('subscription.grace_period.active', false);

        $this->assertLimitMaximums($response, [
            'projects' => null,
            'created_meetings' => null,
            'api_tokens' => null,
        ]);
    }

    #[Test]
    public function grace_period_user_receives_correct_subscription_shape(): void
    {
        // SubscriptionHelpers seeds a grace-period subscription and its receipt.
        $subscription = $this->createGracePeriodSubscription($this->user);
        $this->createReceipt($this->user, $subscription);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'pro')
            ->assertJsonPath('subscription.entitled', true)
            ->assertJsonPath('subscription.subscribed', false)
            ->assertJsonPath('subscription.billing_plan', 'monthly')
            ->assertJsonPath('subscription.next_payment', null)
            ->assertJsonPath('subscription.created_at', null)
            ->assertJsonPath('subscription.grace_period.active', true)
            ->assertJsonPath('subscription.trial.active', false);
    }

    #[Test]
    public function expired_subscription_user_receives_correct_subscription_shape(): void
    {
        // SubscriptionHelpers seeds an expired subscription so the resource falls back to Free.
        $this->createExpiredSubscription($this->user);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'free')
            ->assertJsonPath('subscription.entitled', false)
            ->assertJsonPath('subscription.subscribed', false)
            ->assertJsonPath('subscription.grace_period.active', false)
            ->assertJsonPath('subscription.trial.active', false)
            ->assertJsonPath('subscription.billing_plan', null)
            ->assertJsonPath('subscription.next_payment', null)
            ->assertJsonPath('subscription.created_at', null)
            ->assertJsonPath('subscription.receipts', []);

        $this->assertLimitMaximums($response, [
            'projects' => 3,
            'created_meetings' => 1,
            'api_tokens' => 1,
        ]);
    }

    #[Test]
    public function expired_subscription_user_receives_receipts_when_present(): void
    {
        // SubscriptionHelpers seeds an expired subscription and keeps its receipt visible.
        $subscription = $this->createExpiredSubscription($this->user);
        $receipt = $this->createReceipt($this->user, $subscription);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'free')
            ->assertJsonPath('subscription.subscribed', false)
            ->assertJsonPath('subscription.receipts.0.id', $receipt->id)
            ->assertJsonPath('subscription.receipts.0.receipt_url', $receipt->receipt_url);
    }

    #[Test]
    public function trial_user_receives_pro_plan_in_subscription_shape(): void
    {
        $trialEndsAt = Carbon::now()->addDays(5);

        // SubscriptionHelpers seeds the active trial customer record.
        $this->createTrialCustomer($this->user, $trialEndsAt);

        $response = $this->subscriptionResponse();

        $response->assertJsonPath('subscription.plan', 'pro')
            ->assertJsonPath('subscription.entitled', true)
            ->assertJsonPath('subscription.billing_plan', null)
            ->assertJsonPath('subscription.next_payment', null)
            ->assertJsonPath('subscription.created_at', null)
            ->assertJsonPath('subscription.receipts', [])
            ->assertJsonPath('subscription.trial.active', true)
            ->assertJsonPath('subscription.trial.ends_at', $trialEndsAt->isoFormat('MMMM Do YYYY'))
            ->assertJsonPath('subscription.grace_period.active', false)
            ->assertJsonPath('subscription.limits.projects.max', null)
            ->assertJsonMissingPath('subscription.limits.active_tasks_per_project')
            ->assertJsonMissingPath('subscription.limits.members_per_project');
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function subscriptionResponse(): TestResponse
    {
        return $this->getJson(self::SUBSCRIPTIONS_ROUTE)
            ->assertOk()
            ->assertJsonStructure([
                'subscription' => [
                    'plan',
                    'entitled',
                    'subscribed',
                    'available_plans',
                    'billing_plan',
                    'next_payment',
                    'created_at',
                    'receipts',
                    'trial' => ['active', 'ends_at'],
                    'grace_period' => ['active', 'ends_at'],
                    'limits' => [
                        'projects' => ['used', 'max'],
                        'created_meetings' => ['used', 'max'],
                        'api_tokens' => ['used', 'max'],
                    ],
                ],
            ]);
    }

    /**
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @param  array<string, int|null>  $limits
     */
    private function assertLimitMaximums(TestResponse $response, array $limits): void
    {
        foreach ($limits as $limit => $max) {
            $response->assertJsonPath("subscription.limits.{$limit}.max", $max);
        }
    }
}
