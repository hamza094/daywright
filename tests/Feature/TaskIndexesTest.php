<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaskIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_table_has_the_user_deleted_status_due_composite_index(): void
    {
        $this->assertTrue(
            Schema::hasIndex('tasks', ['user_id', 'deleted_at', 'status_id', 'due_at'])
        );
    }
}
