<?php

declare(strict_types=1);

namespace App\Services\Paddle;

use App\Interfaces\Paddle;
use App\Models\User;
use Override;

final class SubscriptionServiceFake implements Paddle
{
    #[Override]
    public function subscribe(User $user, string $plan): mixed
    {
        return 'https://fake-paylink-url.com';
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
}
