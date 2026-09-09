<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Override;
use Tests\TestCase;

class StageTest extends TestCase
{
    use RefreshDatabase;

    public $project;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // create a user
        $user = User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
        ]);

        Sanctum::actingAs(
            $user,
        );

        Stage::factory()->create(['id' => 1]);
        Stage::factory()->design()->create(['id' => 2]);
        Stage::factory()->develop()->create(['id' => 3]);
        Stage::factory()->testing()->create(['id' => 4]);
        Stage::factory()->deliver()->create(['id' => 5]);
        Stage::factory()->completed()->create(['id' => 6]);
        Stage::factory()->postponed()->create(['id' => 7]);

        $this->project = Project::factory()->for($user)
            ->create(['stage_id' => 1]);
    }

    /** @test */
    public function stages_loaded_sucessfully(): void
    {
        $stages = Stage::all();

        $this->getJson($this->apiV1Route('stages.index'))
            ->assertOk();

        $this->assertEquals($stages->count(), 7);
    }

    /** @test */
    public function allowed_user_can_change_project_stage(): void
    {
        $this->assertEquals('Planing', $this->project->stage->name);

        $newStageId = 2;

        $response = $this->withoutExceptionHandling()
            ->patchJson($this->apiV1ProjectRoute('projects.stage.update', $this->project), [
                'stage' => $newStageId,
            ]);

        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'stage_id' => $newStageId]);

        $this->project->refresh();

        $response->assertJsonPath('data.stage.name', $this->project->stage->name)
            ->assertJsonPath('data.stage.id', $this->project->stage->id)
            ->assertJsonPath('data.stage_updated_at', $this->project->stage_updated_at
                ->setTimezone('UTC')
                ->toIso8601String());
    }

    /** @test */
    public function allowed_user_can_update_postponed_reason(): void
    {
        $postponed_reason = 'Unable to reach';

        $response = $this->withoutExceptionHandling()->patchJson($this->apiV1ProjectRoute('projects.stage.update', $this->project), [
            'stage' => 7,
            'postponed_reason' => $postponed_reason,
        ]);

        $this->project->refresh();

        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'postponed_reason' => $postponed_reason, 'stage_id' => 7]);

        $this->project->refresh();

        $response->assertJsonPath('data.postponed_reason', $this->project->postponed_reason);
    }
}
