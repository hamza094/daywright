<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Project;
use App\Models\User;
use App\Services\Admin\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Override;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\EnablesUserTwoFactor;

class DashboardTest extends TestCase
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

    // Authorization

    #[Test]
    public function non_admin_cannot_access_dashboard_activities(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson($this->apiV1AdminRoute('dashboard.activities'))
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_cannot_access_dashboard_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson($this->apiV1AdminRoute('dashboard.data'))
            ->assertForbidden();
    }

    // Activities

    #[Test]
    public function admin_can_view_recent_activities(): void
    {
        // Create a project which auto-records activity via RecordActivity trait
        Project::factory()->create();

        $response = $this->getJson($this->apiV1AdminRoute('dashboard.activities'))
            ->assertOk();

        $this->assertNotEmpty($response->json('data'));
    }

    #[Test]
    public function activities_are_limited_to_fifteen(): void
    {
        // Create more than 15 projects to generate 15+ activities
        Project::factory()->count(20)->create();

        $response = $this->getJson($this->apiV1AdminRoute('dashboard.activities'))
            ->assertOk();

        $data = $response->json('data');
        $this->assertLessThanOrEqual(15, count($data));
    }

    #[Test]
    public function activities_return_empty_when_none_exist(): void
    {
        $response = $this->getJson($this->apiV1AdminRoute('dashboard.activities'))
            ->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    // Dashboard Data (mocked — repository uses MySQL-specific DATE_FORMAT)

    #[Test]
    public function admin_can_view_dashboard_data_with_monthly_breakdown(): void
    {
        $this->mock(DashboardService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetchDataForMonths')
                ->once()
                ->andReturn([
                    [
                        'month' => now()->subMonth()->format('Y-m'),
                        'projects_count' => 3,
                        'active_projects' => 2,
                        'trashed_projects' => 1,
                        'tasks_count' => 10,
                        'active_tasks' => 7,
                        'trashed_tasks' => 3,
                    ],
                    [
                        'month' => now()->format('Y-m'),
                        'projects_count' => 1,
                        'active_projects' => 1,
                        'trashed_projects' => 0,
                        'tasks_count' => 4,
                        'active_tasks' => 4,
                        'trashed_tasks' => 0,
                    ],
                ]);
        });

        $response = $this->getJson($this->apiV1AdminRoute('dashboard.data'))
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $firstMonth = $data[0];
        $this->assertArrayHasKey('month', $firstMonth);
        $this->assertArrayHasKey('projects_count', $firstMonth);
        $this->assertArrayHasKey('active_projects', $firstMonth);
        $this->assertArrayHasKey('trashed_projects', $firstMonth);
        $this->assertArrayHasKey('tasks_count', $firstMonth);
        $this->assertArrayHasKey('active_tasks', $firstMonth);
        $this->assertArrayHasKey('trashed_tasks', $firstMonth);
    }

    // Backup

    #[Test]
    public function non_admin_cannot_trigger_backup(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson($this->apiV1AdminRoute('backup.database'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_trigger_backup_via_post_route(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:clean');

        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run');

        $this->postJson($this->apiV1AdminRoute('backup.database'))
            ->assertOk()
            ->assertJsonPath('message', 'Backup completed successfully.');
    }

    #[Test]
    public function backup_route_is_not_exposed_via_get(): void
    {
        $this->getJson($this->apiV1AdminRoute('backup.database'))
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonPath('code', 'not_found');
    }

    // Error Handling

    #[Test]
    public function dashboard_data_returns_500_json_on_service_failure(): void
    {
        $this->mock(DashboardService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetchDataForMonths')
                ->once()
                ->andThrow(new RuntimeException('Database connection lost'));
        });

        $this->getJson($this->apiV1AdminRoute('dashboard.data'))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to load dashboard data.')
            ->assertJsonPath('code', 'dashboard_service_error');
    }
}
