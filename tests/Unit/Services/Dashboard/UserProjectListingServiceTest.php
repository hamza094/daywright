<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\DataTransferObjects\Project\DashboardProjectFilters;
use App\Models\Project;
use App\Services\Dashboard\UserProjectListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class UserProjectListingServiceTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function it_uses_the_summary_load_profile_for_user_project_queries(): void
    {
        Project::factory()->for($this->user)->create();

        $projects = app(UserProjectListingService::class)->getUserProjects(
            $this->user,
            DashboardProjectFilters::fromArray([]),
            '-created_at',
        );

        $this->assertCount(2, $projects);

        foreach ($projects as $project) {
            $this->assertTrue($project->relationLoaded('stage'));
            $this->assertFalse($project->relationLoaded('user'));
        }
    }

    #[Test]
    public function it_uses_the_summary_load_profile_for_dashboard_projects(): void
    {
        Project::factory()->for($this->user)->create();

        $projects = app(UserProjectListingService::class)->getDashboardProjects($this->user);

        $this->assertCount(2, $projects);

        foreach ($projects as $project) {
            $this->assertTrue($project->relationLoaded('stage'));
            $this->assertFalse($project->relationLoaded('user'));
        }
    }
}
