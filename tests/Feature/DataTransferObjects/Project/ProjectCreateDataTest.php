<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\Project;

use App\DataTransferObjects\Project\ProjectCreateData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProjectCreateDataTest extends TestCase
{
    #[Test]
    public function it_builds_project_create_data_from_a_payload(): void
    {
        $data = ProjectCreateData::fromArray([
            'name' => 'Launch Board',
            'about' => 'Track launch work across the team.',
            'stage_id' => '4',
            'notes' => '',
            'tasks' => [
                ['title' => 'Create checklist'],
                (object) ['title' => 'Share update'],
                ['title' => ''],
            ],
        ]);

        $this->assertSame([
            'name' => 'Launch Board',
            'about' => 'Track launch work across the team.',
            'stage_id' => 4,
            'notes' => '',
            'tasks' => [
                ['title' => 'Create checklist'],
                ['title' => 'Share update'],
            ],
        ], $data->toArray());

        $this->assertSame([
            'name' => 'Launch Board',
            'about' => 'Track launch work across the team.',
            'stage_id' => 4,
            'notes' => '',
        ], $data->projectAttributes());
    }

    #[Test]
    public function it_ignores_task_entries_without_titles(): void
    {
        $data = ProjectCreateData::fromArray([
            'name' => 'Launch Board',
            'about' => 'Track launch work across the team.',
            'stage_id' => '4',
            'tasks' => [
                ['title' => 'Create checklist'],
                ['name' => 'Missing title'],
                (object) [],
            ],
        ]);

        $this->assertSame([
            ['title' => 'Create checklist'],
        ], $data->starterTasks());
    }
}
