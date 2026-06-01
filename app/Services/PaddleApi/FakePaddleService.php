<?php

declare(strict_types=1);

namespace App\Services\PaddleApi;

use App\Collections\Paddle\DataCollection;
use App\DataTransferObjects\Paddle\PaddleSubscriptionData;
use App\DataTransferObjects\Paddle\UserSubscriptionData;
use App\Interfaces\Paddle;
use App\Interfaces\PaddleApi;
use App\Models\User;
use Override;

class FakePaddleService implements Paddle, PaddleApi
{
    #[Override]
    public function subscribe(User $user, string $plan): mixed
    {
        // Return a fake payment link to mimic Paddle checkout
        return 'https://paddle.example.test/paylink/'.$plan;
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function swap(User $user, string $plan): array
    {
        return [
            'message' => 'Your subscription has been successfully updated to the '.$plan.' plan (fake).',
        ];
    }

    /**
     * @return array{message: string}
     */
    #[Override]
    public function cancel(User $user, string $plan): array
    {
        return [
            'message' => 'Your subscription has been canceled successfully (fake).',
        ];
    }

    public function subscriptionUsersList(UserSubscriptionData $subscriptionData): DataCollection
    {
        // Return a fake DataCollection for testing purposes
        return new DataCollection([
            new PaddleSubscriptionData(
                userId: 1,
                email: 'testuser@example.com',
                signUpDate: now()->toDateString(),
                lastPaymentAmount: '0',
                lastPaymentCurrency: 'USD',
                lastPaymentDate: now()->toDateString(),
                nextPaymentDate: now()->addMonth()->toDateString(),
            ),
        ]);
    }
}
