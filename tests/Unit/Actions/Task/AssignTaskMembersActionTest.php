<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Task;

use App\Actions\Task\AssignTaskMembersAction;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class AssignTaskMembersActionTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function it_only_notifies_new_assignees_once(): void
    {
        Notification::fake();

        $task = $this->project->addTask('test task');
        $member = User::factory()->create();

        $member->members()->syncWithoutDetaching([
            $this->project->id => ['active' => true],
        ]);

        $action = app(AssignTaskMembersAction::class);

        $action->execute($task, $this->project, $this->user, [$member->id]);
        $action->execute($task, $this->project, $this->user, [$member->id]);

        $this->assertSame(1, DB::table('task_user')
            ->where('task_id', $task->id)
            ->where('user_id', $member->id)
            ->count());

        Notification::assertSentToTimes($member, TaskAssigned::class, 1);
    }
}
