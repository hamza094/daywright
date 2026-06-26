<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Project;

use App\Actions\Project\AcceptProjectInvitationAction;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\User;
use App\Notifications\AcceptInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class AcceptProjectInvitationActionTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function it_is_safe_to_repeat(): void
    {
        Notification::fake();

        $invitedUser = User::factory()->create();

        $this->project->invite($invitedUser);

        $action = app(AcceptProjectInvitationAction::class);

        $action->execute($this->project, $invitedUser);
        $action->execute($this->project, $invitedUser);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $invitedUser->id,
            'active' => true,
        ]);

        Notification::assertSentToTimes($this->user, AcceptInvitation::class, 1);
    }

    #[Test]
    public function it_enforces_member_limit_during_invitation_acceptance(): void
    {
        Notification::fake();

        config(['plan-limits.free.max_members_per_project' => 2]);

        $invitedUser1 = User::factory()->create();
        $invitedUser2 = User::factory()->create();
        $invitedUser3 = User::factory()->create();

        $this->project->invite($invitedUser1);
        $this->project->invite($invitedUser2);
        $this->project->invite($invitedUser3);

        $action = app(AcceptProjectInvitationAction::class);

        $action->execute($this->project, $invitedUser1);
        $action->execute($this->project, $invitedUser2);

        $this->expectException(PlanLimitExceededException::class);

        $action->execute($this->project, $invitedUser3);
    }
}
