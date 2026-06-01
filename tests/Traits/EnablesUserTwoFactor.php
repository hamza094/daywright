<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;

trait EnablesUserTwoFactor
{
    protected function enableTwoFactorForUser(User $user): void
    {
        $twoFactor = $user->createTwoFactorAuth();

        $twoFactor->forceFill([
            'label' => "DayWright:{$user->email}",
        ])->save();

        $user->enableTwoFactorAuth();
    }
}
