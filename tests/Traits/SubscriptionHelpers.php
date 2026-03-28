<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Enums\PlanLimitType;
use App\Enums\SubscriptionPlan;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Customer;
use Laravel\Paddle\Receipt;
use Laravel\Paddle\Subscription as PaddleSubscription;

trait SubscriptionHelpers
{
    private function fakePaddleVendorApiResponses(): void
    {
        Http::preventStrayRequests();

        $paddleResponse = Http::response([
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
        ], 200);

        Http::fake([
            'https://sandbox-vendors.paddle.com/*' => $paddleResponse,
            'https://vendors.paddle.com/*' => $paddleResponse,
        ]);
    }

    private function createTrialCustomer(User $user, Carbon $trialEndsAt): Customer
    {
        return Customer::query()->create([
            'billable_id' => (string) $user->getKey(),
            'billable_type' => $user->getMorphClass(),
            'trial_ends_at' => $trialEndsAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProSubscription(User $user, array $overrides = []): PaddleSubscription
    {
        return PaddleSubscription::query()->create(array_merge([
            'billable_id' => (string) $user->getKey(),
            'billable_type' => $user->getMorphClass(),
            'name' => 'DayWright',
            'paddle_id' => fake()->unique()->numberBetween(100000, 999999),
            'paddle_status' => PaddleSubscription::STATUS_ACTIVE,
            'paddle_plan' => (int) config('services.paddle.monthly'),
            'quantity' => 1,
            'trial_ends_at' => null,
            'paused_from' => null,
            'ends_at' => null,
        ], $overrides));
    }

    private function createGracePeriodSubscription(User $user): PaddleSubscription
    {
        return $this->createProSubscription($user, [
            'ends_at' => Carbon::now()->addDays(3),
        ]);
    }

    private function createExpiredSubscription(User $user): PaddleSubscription
    {
        return $this->createProSubscription($user, [
            'ends_at' => Carbon::now()->subDay(),
        ]);
    }

    private function createReceipt(User $user, ?PaddleSubscription $subscription = null): Receipt
    {
        return Receipt::query()->create([
            'billable_id' => (string) $user->getKey(),
            'billable_type' => $user->getMorphClass(),
            'paddle_subscription_id' => $subscription?->paddle_id,
            'checkout_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'order_id' => fake()->unique()->bothify('order-####-????'),
            'amount' => '10.00',
            'tax' => '0.00',
            'currency' => 'USD',
            'quantity' => 1,
            'receipt_url' => fake()->unique()->url(),
            'paid_at' => now()->subDay(),
        ]);
    }

    private function setUpFreeUserAtProjectLimit(): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        $limit = SubscriptionPlan::Free->maxFor(PlanLimitType::Projects) ?? 0;

        Project::factory()->count($limit)->for($user)->create();

        return $user;
    }

    private function setUpExpiredTrialUserAtProjectLimit(): User
    {
        $user = $this->setUpFreeUserAtProjectLimit();

        $this->createTrialCustomer($user, Carbon::now()->subDay());

        return $user;
    }

    private function setUpPostGraceUserAtProjectLimit(): User
    {
        $user = $this->setUpFreeUserAtProjectLimit();

        $this->createExpiredSubscription($user);

        return $user;
    }
}
