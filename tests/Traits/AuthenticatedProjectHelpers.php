<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Http\Middleware\CheckSubscription;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait AuthenticatedProjectHelpers
{
    protected User $user;

    protected Project $project;

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    protected function setUpAuthenticatedUserWithProject(
        array $userAttributes = [],
        bool $disableSubscriptionMiddleware = false,
    ): void {
        /** @var User $user */
        $user = User::factory()->create($userAttributes);

        Sanctum::actingAs($user);

        /** @var Project $project */
        $project = Project::factory()->for($user)->create();

        $this->user = $user;
        $this->project = $project;

        if ($disableSubscriptionMiddleware) {
            $this->withoutMiddleware(CheckSubscription::class);
        }
    }
}
