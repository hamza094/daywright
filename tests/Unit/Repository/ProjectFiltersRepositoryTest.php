<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Models\Project;
use App\Repository\Admin\ProjectFiltersRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectFiltersRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProjectFiltersRepository $repository;

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

        $result = $this->repository->filters(['status' => 'warm'], 50, []);
        $ids = $this->projectIdsFromResult($result);

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

        $result = $this->repository->filters(['status' => 'cold'], 50, []);
        $ids = $this->projectIdsFromResult($result);

        $this->assertCount(2, $ids);
        $this->assertContains($coldProject->id, $ids);
        $this->assertContains($nullScoreProject->id, $ids);
        $this->assertNotContains($hotProject->id, $ids);
        $this->assertNotContains($warmProject->id, $ids);
    }

    /**
     * @param  array{projects: \Illuminate\Contracts\Pagination\LengthAwarePaginator<Project>, appliedFilters: array<int, string>}  $result
     * @return list<int>
     */
    private function projectIdsFromResult(array $result): array
    {
        return collect($result['projects']->items())
            ->pluck('id')
            ->values()
            ->all();
    }
}
