<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Override;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    #[Override]
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            $allowedEmails = config('horizon.admin_emails', []);

            return in_array(optional($user)->email, $allowedEmails, true);
        });
    }
}
