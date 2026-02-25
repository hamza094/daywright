<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatusesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->markAsAdmin();
        $this->enableTwoFactorForUser($this->admin);

        Sanctum::actingAs($this->admin);
    }

    #[Test]
    public function can_create_a_status(): void
    {
        $this->postJson('/api/v1/admin/statuses', [
            'label' => 'In Progress',
            'color' => '#FF5733',
        ])
            ->assertCreated();

        $this->assertDatabaseHas('statuses', ['label' => 'In Progress']);
    }

    #[Test]
    public function cannot_create_status_when_at_max_count(): void
    {
        TaskStatus::factory()->count(6)->sequence(
            ['label' => 'Status 1', 'color' => '#000001'],
            ['label' => 'Status 2', 'color' => '#000002'],
            ['label' => 'Status 3', 'color' => '#000003'],
            ['label' => 'Status 4', 'color' => '#000004'],
            ['label' => 'Status 5', 'color' => '#000005'],
            ['label' => 'Status 6', 'color' => '#000006'],
        )->create();

        $this->postJson('/api/v1/admin/statuses', [
            'label' => 'Overflow',
            'color' => '#FFFFFF',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('label');
    }

    #[Test]
    public function can_update_status_when_at_max_count(): void
    {
        $statuses = TaskStatus::factory()->count(6)->sequence(
            ['label' => 'Status 1', 'color' => '#000001'],
            ['label' => 'Status 2', 'color' => '#000002'],
            ['label' => 'Status 3', 'color' => '#000003'],
            ['label' => 'Status 4', 'color' => '#000004'],
            ['label' => 'Status 5', 'color' => '#000005'],
            ['label' => 'Status 6', 'color' => '#000006'],
        )->create();

        $this->putJson("/api/v1/admin/statuses/{$statuses->first()->id}", [
            'label' => 'Renamed',
            'color' => '#AABBCC',
        ])
            ->assertOk();

        $this->assertDatabaseHas('statuses', [
            'id' => $statuses->first()->id,
            'label' => 'Renamed',
        ]);
    }

    #[Test]
    public function can_update_status_color_only(): void
    {
        $status = TaskStatus::factory()->create([
            'label' => 'Active',
            'color' => '#000000',
        ]);

        $this->putJson("/api/v1/admin/statuses/{$status->id}", [
            'color' => '#FF0000',
        ])
            ->assertOk();

        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'color' => '#FF0000',
        ]);
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
