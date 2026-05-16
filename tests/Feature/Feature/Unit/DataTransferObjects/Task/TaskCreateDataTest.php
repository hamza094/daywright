<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Unit\DataTransferObjects\Task;

use App\DataTransferObjects\Task\TaskCreateData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TaskCreateDataTest extends TestCase
{
    #[Test]
    public function it_builds_task_create_data_from_a_payload(): void
    {
        $data = TaskCreateData::fromArray([
            'title' => 'Draft QA checklist',
            'status_id' => 2,
            'ignored' => 'value',
        ]);

        $this->assertSame([
            'title' => 'Draft QA checklist',
            'status_id' => 2,
        ], $data->toArray());

        $this->assertSame([
            'title' => 'Draft QA checklist',
            'status_id' => 2,
            'user_id' => 10,
        ], $data->toCreateAttributes(10));
    }
}
