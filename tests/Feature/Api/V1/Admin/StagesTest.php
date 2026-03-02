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
        $this->postJson('/api/v1/admin/stages', ['name' => 'Planning'])
            ->assertCreated();

        $this->assertDatabaseHas('stages', ['name' => 'Planning']);
    }

    #[Test]
    public function cannot_create_duplicate_stage_name(): void
    {
        Stage::factory()->create(['name' => 'Planning']);

        $this->postJson('/api/v1/admin/stages', ['name' => 'Planning'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function can_update_stage_with_same_name(): void
    {
        $stage = Stage::factory()->create(['name' => 'Planning']);

        $this->putJson("/api/v1/admin/stages/{$stage->id}", ['name' => 'Planning'])
            ->assertOk();
    }

    #[Test]
    public function cannot_update_stage_to_existing_name(): void
    {
        Stage::factory()->create(['name' => 'Planning']);
        $stage = Stage::factory()->create(['name' => 'Development']);

        $this->putJson("/api/v1/admin/stages/{$stage->id}", ['name' => 'Planning'])
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

        $this->postJson('/api/v1/admin/stages', ['name' => 'Stage 6'])
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

        $this->putJson("/api/v1/admin/stages/{$stages->first()->id}", ['name' => 'Renamed Stage'])
            ->assertOk();
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
