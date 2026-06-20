<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\Project;

use App\DataTransferObjects\Project\DashboardProjectFilters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DashboardProjectFiltersTest extends TestCase
{
    #[Test]
    public function it_builds_dashboard_project_filters_from_a_payload(): void
    {
        $filters = DashboardProjectFilters::fromArray([
            'search' => 'frontend',
            'member' => true,
            'abandoned' => 0,
        ]);

        $this->assertSame([
            'search' => 'frontend',
            'member' => true,
            'abandoned' => false,
        ], $filters->toArray());
    }
}
