<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Enums\Subscription\PlanLimitType;
use App\Models\Project;
use App\Models\User;
use App\Notifications\AcceptInvitation;
use App\Notifications\ProjectInvitation;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Auth;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    /**
     * Invite a user by email, enforcing project plan limits.
     */
    public function sendInvitationByEmail(Project $project, string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();

        $this->planLimitService->executeWithinProjectLimit(
            PlanLimitType::MembersPerProject,
            $project,
            function (Project $lockedProject) use ($user): void {
                $this->sendInvitation($user, $lockedProject);
            }
        );
    }

    public function sendInvitation(User $user, Project $project): void
    {
        $this->validateInvitation($project, $user);

        $project->invite($user);

        DB::afterCommit(function () use ($project, $user): void {
            try {
                $this->dispatchInvitationSideEffects($project, $user);
            } catch (Throwable $e) {
                report($e);
            }
        });

    }

    public function acceptInvitation(Project $project): void
    {
        $user = Auth::user();

        DB::beginTransaction();

        try {

            $this->activateMembership($project, $user);

            $this->recordActivity($project, $user, 'invitation_accepted');

            $project->user->notify(
                new AcceptInvitation(
                    $project->name,
                    $project->path(),
                    $user->getNotifierData()
                ));

            DB::commit();

        } catch (Exception $ex) {

            DB::rollBack();

            throw $ex;
        }
    }

    public function removeMember(User $user, Project $project): void
    {
        $this->validateRemoval($project, $user);

        DB::transaction(function () use ($project, $user): void {

            $project->members()->detach($user);

            $this->recordActivity($project, $user, 'member_removed');
        });
    }

    /**
     * Get the pending members for the given project.
     *
     * @return EloquentCollection<int, User>
     */
    public function pendingMembers(Project $project): EloquentCollection
    {
        return $project->members()
            ->wherePivot('active', false)
            ->withPivot('created_at')
            ->get();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function usersSearch(Request $request): Collection
    {
        $searchTerm = (string) $request->string('query')->trim();

        return User::query()
            ->whereAny(['name', 'email'], 'LIKE', '%'.$searchTerm.'%')
            ->select('uuid', 'name', 'email')
            ->limit(5)
            ->get();
    }

    /**
     * Reject an invitation for a user.
     */
    public function rejectInvitation(Project $project, User $user): void
    {
        $project->members()->detach($user);
    }

    /**
     * Cancel an invitation for a user as a project owner.
     */
    public function cancelInvitation(Project $project, User $user): void
    {
        if ($user->cannot('canAcceptInvitation', $project)) {
            abort(403);
        }

        $project->members()->detach($user);
    }

    protected function recordActivity(Project $project, User $user, string $msg): void
    {
        $project->recordActivity($msg, [$user->id]);
    }

    protected function activateMembership(Project $project, Authenticatable $user): void
    {
        $user->members()->updateExistingPivot($project, ['active' => true]);
    }

    protected function validateInvitation(Project $project, User $user): void
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

    protected function validateRemoval(Project $project, User $user): void
    {
        if (! $project->activeMembers()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'This user is not an active member of the project.',
            ]);
        }
    }

    private function dispatchInvitationSideEffects(Project $project, User $user): void
    {
        $this->recordActivity($project, $user, 'invitation_sent');

        $user->notify(new ProjectInvitation(
            $project->name,
            $project->path(),
            $project->user->getNotifierData()
        ));
    }
}
