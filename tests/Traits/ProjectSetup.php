<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Enums\ProjectStage;
use App\Enums\TaskSystemStatus;
use App\Models\Project;
use App\Models\Stage;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

trait ProjectSetup
{
    public Project $project;

    public User $user;

    public TaskStatus $status;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
        ]);

        $this->user = $user;

        Sanctum::actingAs(
            $this->user,
        );

        $status = TaskStatus::factory()->create(['id' => TaskSystemStatus::Pending->value]);
        $this->status = $status;

        $stage = Stage::factory()->create(['id' => ProjectStage::Planning->value]);
        $project = Project::factory()->for($this->user)->create(['stage_id' => $stage->id]);
        $this->project = $project;

        // if ($this instanceof \Tests\Feature\TaskTest) {
        // $this->status = TaskStatus::factory()->create();
        // }

        $middlewaresToRemove = [
            \App\Http\Middleware\CheckSubscription::class,
            \App\Http\Middleware\RequireSessionAuth::class,
        ];

        $this->withoutMiddleware($middlewaresToRemove);
    }
}
