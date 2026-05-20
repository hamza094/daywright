<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\DataTransferObjects\Project\AdminProjectFilters;
use App\Models\Project;
use App\Repository\Admin\ProjectFiltersRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectFiltersRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProjectFiltersRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(ProjectFiltersRepository::class);
    }

    #[Test]
    public function it_filters_projects_by_warm_status_using_health_score_range(): void
    {
        /** @var Project $warmProject */
        $warmProject = Project::factory()->create(['health_score' => 55]);
        /** @var Project $hotProject */
        $hotProject = Project::factory()->create(['health_score' => 80]);
        /** @var Project $coldProject */
        $coldProject = Project::factory()->create(['health_score' => 20]);

        $projects = $this->repository->filter(AdminProjectFilters::fromArray(['status' => 'warm']), 50);
        $ids = $this->projectIds($projects);

        $this->assertCount(1, $ids);
        $this->assertSame([$warmProject->id], $ids);
        $this->assertNotContains($hotProject->id, $ids);
        $this->assertNotContains($coldProject->id, $ids);
    }

    #[Test]
    public function it_filters_projects_by_cold_status_including_null_health_score(): void
    {
        /** @var Project $coldProject */
        $coldProject = Project::factory()->create(['health_score' => 30]);
        /** @var Project $nullScoreProject */
        $nullScoreProject = Project::factory()->create(['health_score' => null]);
        /** @var Project $hotProject */
        $hotProject = Project::factory()->create(['health_score' => 82]);
        /** @var Project $warmProject */
        $warmProject = Project::factory()->create(['health_score' => 58]);

        $projects = $this->repository->filter(AdminProjectFilters::fromArray(['status' => 'cold']), 50);
        $ids = $this->projectIds($projects);

        $this->assertCount(2, $ids);
        $this->assertContains($coldProject->id, $ids);
        $this->assertContains($nullScoreProject->id, $ids);
        $this->assertNotContains($hotProject->id, $ids);
        $this->assertNotContains($warmProject->id, $ids);
    }

    #[Test]
    public function it_defaults_to_newest_first_when_no_sort_is_provided(): void
    {
        /** @var Project $oldProject */
        $oldProject = Project::factory()->create(['created_at' => now()->subDays(3)]);
        /** @var Project $newProject */
        $newProject = Project::factory()->create(['created_at' => now()]);

        $projects = $this->repository->filter(AdminProjectFilters::fromArray([]), 50);
        $ids = $this->projectIds($projects);

        $this->assertSame($newProject->id, $ids[0]);
        $this->assertContains($oldProject->id, $ids);
    }

    #[Test]
    public function it_sorts_projects_by_descending_health_score_when_requested(): void
    {
        /** @var Project $lowHealthProject */
        $lowHealthProject = Project::factory()->create(['health_score' => 20]);
        /** @var Project $highHealthProject */
        $highHealthProject = Project::factory()->create(['health_score' => 85]);

        $projects = $this->repository->filter(AdminProjectFilters::fromArray(['sort' => '-health_score']), 50);
        $ids = $this->projectIds($projects);

        $this->assertSame($highHealthProject->id, $ids[0]);
        $this->assertContains($lowHealthProject->id, $ids);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Project>  $projects
     * @return list<int>
     */
    private function projectIds($projects): array
    {
        return collect($projects->items())
            ->pluck('id')
            ->values()
            ->all();
    }
}
