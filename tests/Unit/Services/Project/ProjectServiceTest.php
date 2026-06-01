<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Project;

use App\Models\Meeting;
use App\Services\Project\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class ProjectServiceTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function it_uses_the_project_resource_load_profile_for_project_details(): void
    {
        Meeting::factory()->for($this->project)->for($this->user)->create();

        $project = app(ProjectService::class)->loadForDetails($this->project->fresh());

        $this->assertTrue($project->relationLoaded('user'));
        $this->assertTrue($project->relationLoaded('stage'));
        $this->assertTrue($project->relationLoaded('activeMembers'));
        $this->assertTrue($project->relationLoaded('limitedActivities'));
        $this->assertFalse($project->relationLoaded('meetings'));

        $activity = $project->limitedActivities->first();

        $this->assertNotNull($activity);
        $this->assertTrue($activity->relationLoaded('user'));
        $this->assertTrue($activity->relationLoaded('subject'));
    }
}
