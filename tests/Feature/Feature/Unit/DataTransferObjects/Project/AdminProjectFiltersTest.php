<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Unit\DataTransferObjects\Project;

use App\DataTransferObjects\Project\AdminProjectFilters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminProjectFiltersTest extends TestCase
{
    #[Test]
    public function it_builds_admin_project_filters_from_a_payload(): void
    {
        $filters = AdminProjectFilters::fromArray([
            'search' => 'Alpha',
            'state' => 'TRASHED',
            'status' => 'HOT',
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'stage' => '4',
            'members' => '1',
            'tasks' => false,
            'sort' => '-created_at',
        ]);

        $this->assertSame([
            'search' => 'Alpha',
            'state' => 'trashed',
            'status' => 'hot',
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'stage' => 4,
            'members' => true,
            'tasks' => false,
            'sort' => '-created_at',
        ], $filters->toArray());
    }
}
