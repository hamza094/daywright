<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Laravel\Paddle\Customer;
use Laravel\Paddle\Receipt;
use Laravel\Paddle\Subscription as PaddleSubscription;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTrialUser();
        $this->seedProUser();
        $this->seedGracePeriodUser();
    }

    private function seedTrialUser(): void
    {
        $user = $this->firstOrCreateUser('Trial User', 'trial@daywright.test', 'trial.user');

        $this->upsertCustomer(
            $user,
            Carbon::now()->addDays((int) config('plan-limits.trial.duration_days', 7)),
        );
    }

    private function seedProUser(): void
    {
        $user = $this->firstOrCreateUser('Pro User', 'pro@daywright.test', 'pro.user');

        $this->upsertCustomer($user);

        $subscription = $this->upsertSubscription($user);

        $this->upsertReceipt($user, $subscription);
    }

    private function seedGracePeriodUser(): void
    {
        $user = $this->firstOrCreateUser('Grace Period User', 'grace@daywright.test', 'grace.period.user');

        $this->upsertCustomer($user);

        $subscription = $this->upsertSubscription($user, Carbon::now()->addDays(3));

        $this->upsertReceipt($user, $subscription);
    }

    private function firstOrCreateUser(string $name, string $email, string $username): User
    {
        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            User::factory()->make([
                'name' => $name,
                'username' => $username,
                'avatar_path' => "https://eu.ui-avatars.com/api/?name={$name}",
                'email' => $email,
            ])->getAttributes(),
        );

        return $user;
    }

    private function upsertCustomer(User $user, ?Carbon $trialEndsAt = null): void
    {
        Customer::query()->updateOrCreate(
            [
                'billable_id' => (string) $user->getKey(),
                'billable_type' => $user->getMorphClass(),
            ],
            [
                'trial_ends_at' => $trialEndsAt,
            ],
        );
    }

    private function upsertSubscription(User $user, ?Carbon $endsAt = null): PaddleSubscription
    {
        /** @var PaddleSubscription $subscription */
        $subscription = PaddleSubscription::query()->firstOrNew([
            'billable_id' => (string) $user->getKey(),
            'billable_type' => $user->getMorphClass(),
            'name' => config('services.paddle.subscription_name', 'DayWright'),
        ]);

        $subscription->paddle_id ??= fake()->unique()->numberBetween(100000, 999999);
        $subscription->paddle_status = PaddleSubscription::STATUS_ACTIVE;
        $subscription->paddle_plan = (int) config('services.paddle.monthly');
        $subscription->quantity = 1;
        $subscription->trial_ends_at = null;
        $subscription->paused_from = null;
        $subscription->ends_at = $endsAt;
        $subscription->save();

        return $subscription;
    }

    private function upsertReceipt(User $user, PaddleSubscription $subscription): void
    {
        /** @var Receipt $receipt */
        $receipt = Receipt::query()->firstOrNew([
            'billable_id' => (string) $user->getKey(),
            'billable_type' => $user->getMorphClass(),
            'paddle_subscription_id' => $subscription->paddle_id,
        ]);

        $receipt->checkout_id ??= (string) fake()->unique()->numberBetween(100000, 999999);
        $receipt->order_id ??= fake()->unique()->bothify('order-####-????');
        $receipt->receipt_url ??= fake()->unique()->url();
        $receipt->amount = (string) config('services.paddle.prices.monthly', 12);
        $receipt->tax = '0.00';
        $receipt->currency = config('services.paddle.prices.currency', 'USD');
        $receipt->quantity = 1;
        $receipt->paid_at = Carbon::now()->subDays(15);
        $receipt->save();
    }
}
