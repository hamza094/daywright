<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectInvitation;
use App\Services\Subscription\PlanLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class SendProjectInvitationAction
{
    public function __construct(
        private PlanLimitService $planLimitService,
    ) {}

    public function execute(Project $project, string $email): User
    {
        $user = User::query()->where('email', $email)->firstOrFail();

        $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::MembersPerProject,
            $project,
            function (Project $lockedProject) use ($user): void {
                $this->validateInvitation($lockedProject, $user);

                $lockedProject->invite($user);

                DB::afterCommit(function () use ($lockedProject, $user): void {
                    try {
                        $this->dispatchInvitationSideEffects($lockedProject, $user);
                    } catch (Throwable $throwable) {
                        report($throwable);
                    }
                });
            }
        );

        return $user;
    }

    private function validateInvitation(Project $project, User $user): void
    {
        throw_if(
            $project->members()->where('user_id', $user->id)->exists(),
            ValidationException::withMessages([
                'invitation' => 'Project invitation already sent to a user.',
            ])
        );

        throw_if(
            $user->is($project->user),
            ValidationException::withMessages([
                'invitation' => "Can't send an invitation to the project owner.",
            ])
        );
    }

    private function dispatchInvitationSideEffects(Project $project, User $user): void
    {
        $project->recordActivity('invitation_sent', [$user->id]);

        $user->notify(new ProjectInvitation(
            $project->name,
            $project->path(),
            $project->user->getNotifierData()
        ));
    }
}
