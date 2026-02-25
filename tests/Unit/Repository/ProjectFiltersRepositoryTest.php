<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Models\Project;
use App\Repository\Admin\ProjectFiltersRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectFiltersRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_filters_projects_by_warm_status_using_health_score_range(): void
    {
        $warmProject = Project::factory()->create(['health_score' => 55]);
        Project::factory()->create(['health_score' => 80]);
        Project::factory()->create(['health_score' => 20]);

        $request = Request::create('/api/v1/admin/projects', 'GET', [
            'status' => 'warm',
        ]);

        $result = app(ProjectFiltersRepository::class)->filters($request, 50, []);

        $ids = collect($result['projects']->items())->pluck('id')->values()->all();

        $this->assertSame([$warmProject->id], $ids);
    }

    #[Test]
    public function it_filters_projects_by_cold_status_including_null_health_score(): void
    {
        $coldProject = Project::factory()->create(['health_score' => 30]);
        $nullScoreProject = Project::factory()->create(['health_score' => null]);
        Project::factory()->create(['health_score' => 82]);
        Project::factory()->create(['health_score' => 58]);

        $request = Request::create('/api/v1/admin/projects', 'GET', [
            'status' => 'cold',
        ]);

        $result = app(ProjectFiltersRepository::class)->filters($request, 50, []);

        $ids = collect($result['projects']->items())->pluck('id')->all();

        $this->assertContains($coldProject->id, $ids);
        $this->assertContains($nullScoreProject->id, $ids);
        $this->assertCount(2, $ids);
    }
}
