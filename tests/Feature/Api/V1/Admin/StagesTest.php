<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->enableTwoFactorForUser($this->admin);

        Sanctum::actingAs($this->admin);
    }

    #[Test]
    public function can_create_a_stage(): void
    {
        $this->postJson($this->apiV1AdminRoute('stages.store'), ['name' => 'Planning'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Planning');

        $this->assertDatabaseHas('stages', ['name' => 'Planning']);
    }

    #[Test]
    public function cannot_create_duplicate_stage_name(): void
    {
        Stage::factory()->create(['name' => 'Planning']);

        $this->postJson($this->apiV1AdminRoute('stages.store'), ['name' => 'Planning'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function can_update_stage_with_same_name(): void
    {
        $stage = Stage::factory()->create(['name' => 'Planning']);

        $this->putJson($this->apiV1AdminRoute('stages.update', ['stage' => $stage]), ['name' => 'Planning'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Planning');
    }

    #[Test]
    public function cannot_update_stage_to_existing_name(): void
    {
        Stage::factory()->create(['name' => 'Planning']);
        $stage = Stage::factory()->create(['name' => 'Development']);

        $this->putJson($this->apiV1AdminRoute('stages.update', ['stage' => $stage]), ['name' => 'Planning'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function cannot_create_more_than_five_stages(): void
    {
        Stage::factory()->count(5)->sequence(
            ['name' => 'Stage 1'],
            ['name' => 'Stage 2'],
            ['name' => 'Stage 3'],
            ['name' => 'Stage 4'],
            ['name' => 'Stage 5'],
        )->create();

        $this->postJson($this->apiV1AdminRoute('stages.store'), ['name' => 'Stage 6'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function can_update_stage_when_at_max_count(): void
    {
        $stages = Stage::factory()->count(5)->sequence(
            ['name' => 'Stage 1'],
            ['name' => 'Stage 2'],
            ['name' => 'Stage 3'],
            ['name' => 'Stage 4'],
            ['name' => 'Stage 5'],
        )->create();

        $this->putJson($this->apiV1AdminRoute('stages.update', ['stage' => $stages->first()]), ['name' => 'Renamed Stage'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Stage');
    }

    #[Test]
    public function can_delete_a_stage_with_message_response(): void
    {
        $stage = Stage::factory()->create(['name' => 'Planning']);

        $this->deleteJson($this->apiV1AdminRoute('stages.destroy', ['stage' => $stage]))
            ->assertOk()
            ->assertJsonPath('message', 'Stage deleted successfully');

        $this->assertDatabaseMissing('stages', ['id' => $stage->id]);
    }

    private function enableTwoFactorForUser(User $user): void
    {
        $twoFactor = $user->createTwoFactorAuth();

        $twoFactor->forceFill([
            'label' => "DayWright:{$user->email}",
        ])->save();

        $user->enableTwoFactorAuth();
    }
}
