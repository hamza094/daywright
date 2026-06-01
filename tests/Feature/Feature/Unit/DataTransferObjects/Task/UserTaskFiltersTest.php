<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Unit\DataTransferObjects\Task;

use App\DataTransferObjects\Task\UserTaskFilters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserTaskFiltersTest extends TestCase
{
    #[Test]
    public function it_builds_user_task_filters_and_detects_any_enabled_filter(): void
    {
        $filters = UserTaskFilters::fromArray([
            'user_created' => 1,
            'task_assigned' => false,
            'completed' => true,
            'overdue' => 0,
            'remaining' => false,
        ]);

        $this->assertSame([
            'user_created' => true,
            'task_assigned' => false,
            'completed' => true,
            'overdue' => false,
            'remaining' => false,
        ], $filters->toArray());

        $this->assertTrue($filters->hasAnyFilter());
    }
}
