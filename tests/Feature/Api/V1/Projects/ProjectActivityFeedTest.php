<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ProjectActivityFeedTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    #[Test]
    public function activity_feed_keeps_the_subject_of_a_soft_deleted_task(): void
    {
        $task = $this->project->addTask('archived task');

        $task->delete();

        $this->assertSoftDeleted($task);

        $response = $this->getJson($this->apiV1ProjectRoute('projects.activities', $this->project, query: ['filter' => ['type' => 'tasks']]))
            ->assertOk();

        $taskSubjects = collect($response->json('data'))->pluck('subject');

        $this->assertNotEmpty($taskSubjects);
        $taskSubjects->each(fn (array $subject) => $this->assertSame(['type' => 'Task', 'id' => $task->id], $subject));
    }
}
