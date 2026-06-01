<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Project;

use App\Actions\Project\BuildAdminProjectAppliedFiltersAction;
use App\DataTransferObjects\Project\AdminProjectFilters;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BuildAdminProjectAppliedFiltersActionTest extends TestCase
{
    use RefreshDatabase;

    private BuildAdminProjectAppliedFiltersAction $action;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(BuildAdminProjectAppliedFiltersAction::class);
    }

    #[Test]
    public function it_builds_labels_separately_from_project_query_composition(): void
    {
        /** @var Stage $stage */
        $stage = Stage::factory()->create(['name' => 'Delivery']);

        $filters = AdminProjectFilters::fromArray([
            'search' => 'Alpha',
            'state' => 'trashed',
            'status' => 'warm',
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'stage' => $stage->id,
            'members' => true,
            'tasks' => true,
            'sort' => '-created_at',
        ]);

        $this->assertSame([
            'Sort by newest',
            'Search in all',
            'Filter by Trashed',
            'Filter by Active Members',
            'Filter by Tasks',
            'Filter by Stage: Delivery',
            'Filter from 2026-05-01 to 2026-05-31',
            'Filter by status warm',
        ], $this->action->execute($filters));
    }

    #[Test]
    public function it_builds_human_readable_labels_for_supported_project_sort_values(): void
    {
        $filters = AdminProjectFilters::fromArray([
            'sort' => '-health_score',
        ]);

        $this->assertSame([
            'Sort by health score (high-low)',
        ], $this->action->execute($filters));
    }

    #[Test]
    public function it_uses_the_closed_postponed_stage_label_for_zero_stage(): void
    {
        $filters = AdminProjectFilters::fromArray([
            'stage' => 0,
        ]);

        $this->assertSame([
            'Filter by Stage: Clo/Pos',
        ], $this->action->execute($filters));
    }
}
