<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Actions\Project\AcceptProjectInvitationAction;
use App\Actions\Project\CancelProjectInvitationAction;
use App\Actions\Project\RejectProjectInvitationAction;
use App\Actions\Project\RemoveProjectMemberAction;
use App\Actions\Project\SendProjectInvitationAction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InvitationService
{
    public function __construct(
        private readonly SendProjectInvitationAction $sendProjectInvitationAction,
        private readonly AcceptProjectInvitationAction $acceptProjectInvitationAction,
        private readonly RejectProjectInvitationAction $rejectProjectInvitationAction,
        private readonly CancelProjectInvitationAction $cancelProjectInvitationAction,
        private readonly RemoveProjectMemberAction $removeProjectMemberAction,
    ) {}

    public function sendInvitationByEmail(Project $project, string $email): User
    {
        return $this->sendProjectInvitationAction->execute($project, $email);
    }

    public function acceptInvitation(Project $project, User $user): void
    {
        $this->acceptProjectInvitationAction->execute($project, $user);
    }

    public function removeMember(User $user, Project $project): void
    {
        $this->removeProjectMemberAction->execute($project, $user);
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
        $this->rejectProjectInvitationAction->execute($project, $user);
    }

    /**
     * Cancel an invitation for a user as a project owner.
     */
    public function cancelInvitation(Project $project, User $user): void
    {
        $this->cancelProjectInvitationAction->execute($project, $user);
    }
}
