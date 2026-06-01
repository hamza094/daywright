<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityResourceTest extends TestCase
{
    #[Test]
    public function it_formats_project_updates_with_the_shared_suffix(): void
    {
        $activity = new Activity([
            'description' => 'updated_project',
            'changes' => ['after' => ['name' => 'Renamed project']],
            'subject_type' => Project::class,
        ]);

        $result = (new ActivityResource($activity))->toArray(request());

        $this->assertSame('Project Name updated', $result['description']);
    }

    #[Test]
    public function it_formats_task_updates_with_the_shared_suffix(): void
    {
        $activity = new Activity([
            'description' => 'updated_task',
            'changes' => ['after' => ['priority_level' => 'high']],
            'subject_type' => Task::class,
        ]);
        $activity->setRelation('subject', (object) [
            'id' => 15,
            'title' => 'Brief',
        ]);

        $result = (new ActivityResource($activity))->toArray(request());

        $this->assertSame("Task 'Brief' Priority Level updated", $result['description']);
    }

    #[Test]
    public function it_formats_meeting_updates_with_the_shared_suffix(): void
    {
        $activity = new Activity([
            'description' => 'updated_meeting',
            'subject_type' => Meeting::class,
        ]);
        $activity->setRelation('subject', (object) [
            'id' => 8,
            'topic' => 'Sprint review',
        ]);

        $result = (new ActivityResource($activity))->toArray(request());

        $this->assertSame('Meeting Sprint review updated', $result['description']);
    }
}
