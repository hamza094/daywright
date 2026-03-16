<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

final class CreateTrialCustomer
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if ($user->customer()->exists()) {
            return;
        }

        $user->createAsCustomer([
            'trial_ends_at' => now()->addDays((int) config('plan-limits.trial.duration_days', 7)),
        ]);
    }
}
