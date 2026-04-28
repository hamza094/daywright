<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class TaskStatusTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function authenticated_user_can_view_task_statuses(): void
    {
        $status = TaskStatus::factory()->create();

        $this->getJson('/api/v1/task-statuses')
            ->assertOk()
            ->assertJsonStructure([
                'statuses',
                'due_notifies',
            ])
            ->assertJsonFragment([
                'id' => $status->id,
                'label' => $status->label,
                'color' => $status->color,
            ]);
    }
}
