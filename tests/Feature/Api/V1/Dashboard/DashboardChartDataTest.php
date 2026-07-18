<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class DashboardChartDataTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function auth_user_can_get_chart_data(): void
    {
        // Create projects with different dates for testing
        Project::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subMonth(),
        ]);

        Project::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        // Create a trashed project
        Project::factory()->create([
            'user_id' => $this->user->id,
            'deleted_at' => now(),
        ]);

        // Create a project where user is a member
        $memberProject = Project::factory()->create();
        DB::table('project_members')->insert([
            'project_id' => $memberProject->id,
            'user_id' => $this->user->id,
            'active' => 1,
        ]);

        $response = $this->getJson($this->apiV1Route('dashboard.chart-data'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'active_projects',
                    'trashed_projects',
                    'member_projects',
                    'total_projects',
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsInt($data['active_projects']);
        $this->assertIsInt($data['trashed_projects']);
        $this->assertIsInt($data['member_projects']);
        $this->assertIsInt($data['total_projects']);
        $this->assertEquals(
            $data['active_projects'] + $data['trashed_projects'] + $data['member_projects'],
            $data['total_projects']
        );
    }

    /** @test */
    public function chart_data_respects_year_month_filters(): void
    {
        // Arrange
        $currentYear = now()->year;
        $currentMonth = now()->month;
        $previousYear = $currentYear - 1;

        // Delete the default project from ProjectSetup trait to start clean
        $this->project->delete();

        // Create project in current year/month
        Project::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        // Create project in previous year
        Project::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => Carbon::create($previousYear, 6, 15),
        ]);

        // Act & Assert
        // 1. Current year filter
        $currentYearResponse = $this->getJson($this->apiV1Route('dashboard.chart-data', query: ['year' => $currentYear]));
        $this->assertEquals(1, $currentYearResponse->json('data.active_projects'));

        // 2. Previous year filter
        $previousYearResponse = $this->getJson($this->apiV1Route('dashboard.chart-data', query: ['year' => $previousYear]));
        $this->assertEquals(1, $previousYearResponse->json('data.active_projects'));

        // 3. Current year and month filter
        $monthFilterResponse = $this->getJson($this->apiV1Route('dashboard.chart-data', query: [
            'year' => $currentYear,
            'month' => $currentMonth,
        ]));
        $this->assertEquals(1, $monthFilterResponse->json('data.active_projects'));

        // 4. No filters
        $noFilterResponse = $this->getJson($this->apiV1Route('dashboard.chart-data'));
        $this->assertEquals(2, $noFilterResponse->json('data.active_projects'));

        // Assert all responses were successful
        collect([
            $currentYearResponse,
            $previousYearResponse,
            $monthFilterResponse,
            $noFilterResponse,
        ])->each->assertOk();
    }

    /** @test */
    public function chart_data_requires_year_when_month_is_provided(): void
    {
        $this->getJson($this->apiV1Route('dashboard.chart-data', query: [
            'month' => now()->month,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    /** @test */
    public function chart_data_validates_year_and_month_filters(): void
    {
        $response = $this->getJson($this->apiV1Route('dashboard.chart-data', query: [
            'year' => 'invalid',
            'month' => 13,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['year', 'month']);
    }

    /** @test */
    public function chart_data_rejects_unsupported_top_level_query_parameters(): void
    {
        $this->getJson($this->apiV1Route('dashboard.chart-data', query: [
            'year' => now()->year,
            'sort' => 'created_at',
            'include' => 'projects',
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'include', 'random']);
    }
}
