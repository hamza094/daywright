<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\Task;

use App\DataTransferObjects\Task\AdminTaskFilters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminTaskFiltersTest extends TestCase
{
    #[Test]
    public function it_builds_admin_task_filters_from_a_payload(): void
    {
        $filters = AdminTaskFilters::fromArray([
            'search' => 'Unique Project',
            'state' => 'TRASHED',
            'sort' => '-created_at',
        ]);

        $this->assertSame([
            'search' => 'Unique Project',
            'state' => 'trashed',
            'sort' => '-created_at',
        ], $filters->toArray());
    }
}
