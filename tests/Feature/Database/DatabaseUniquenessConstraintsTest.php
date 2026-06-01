<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Meeting;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseUniquenessConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_member_pairs_must_be_unique(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $attributes = [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('project_members')->insert($attributes);

        $this->expectException(QueryException::class);

        DB::table('project_members')->insert($attributes);
    }

    public function test_task_assignee_pairs_must_be_unique(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();

        $attributes = [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('task_user')->insert($attributes);

        $this->expectException(QueryException::class);

        DB::table('task_user')->insert($attributes);
    }

    public function test_message_recipient_pairs_must_be_unique(): void
    {
        $message = Message::factory()->create();
        $user = User::factory()->create();

        $attributes = [
            'message_id' => $message->id,
            'user_id' => $user->id,
        ];

        DB::table('message_user')->insert($attributes);

        $this->expectException(QueryException::class);

        DB::table('message_user')->insert($attributes);
    }

    public function test_zoom_meeting_ids_must_be_unique(): void
    {
        $meeting = Meeting::factory()->create();

        $this->expectException(QueryException::class);

        Meeting::factory()->create([
            'meeting_id' => $meeting->meeting_id,
        ]);
    }
}
