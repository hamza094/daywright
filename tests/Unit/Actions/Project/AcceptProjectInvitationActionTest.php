<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Project;

use App\Actions\Project\AcceptProjectInvitationAction;
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
}
