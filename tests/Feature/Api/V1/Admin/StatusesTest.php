<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\EnablesUserTwoFactor;

class StatusesTest extends TestCase
{
    use EnablesUserTwoFactor;
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
    public function can_create_a_status(): void
    {
        $this->postJson($this->apiV1AdminRoute('statuses.store'), [
            'label' => 'In Progress',
            'color' => '#FF5733',
        ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'In Progress')
            ->assertJsonPath('data.color', '#FF5733');

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

        $this->postJson($this->apiV1AdminRoute('statuses.store'), [
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

        $this->putJson($this->apiV1AdminRoute('statuses.update', ['status' => $statuses->first()]), [
            'label' => 'Renamed',
            'color' => '#AABBCC',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Renamed')
            ->assertJsonPath('data.color', '#AABBCC');

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

        $this->putJson($this->apiV1AdminRoute('statuses.update', ['status' => $status]), [
            'color' => '#FF0000',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Active')
            ->assertJsonPath('data.color', '#FF0000');

        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'color' => '#FF0000',
        ]);
    }

    #[Test]
    public function can_delete_a_status_with_message_response(): void
    {
        $status = TaskStatus::factory()->create([
            'label' => 'Active',
            'color' => '#000000',
        ]);

        $this->deleteJson($this->apiV1AdminRoute('statuses.destroy', ['status' => $status]))
            ->assertOk()
            ->assertJsonPath('message', 'Status deleted successfully.');

        $this->assertDatabaseMissing('statuses', ['id' => $status->id]);
    }
}
