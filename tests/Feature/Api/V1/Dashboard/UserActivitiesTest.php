<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\Activity;
use App\Models\User;
use App\Repository\Dashboard\ActivityRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class UserActivitiesTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function activities_endpoint_validates_date_parameters(): void
    {
        // Test missing parameters
        $response = $this->getJson('api/v1/dashboard/activities');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);

        // Test invalid date format
        $response = $this->getJson('api/v1/dashboard/activities?start_date=01-08-2025&end_date=15-08-2025');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);

        // Test end date before start date
        $response = $this->getJson('api/v1/dashboard/activities?start_date=2025-08-15&end_date=2025-08-01');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        // Test valid format but invalid dates
        $response = $this->getJson('api/v1/dashboard/activities?start_date=2025-13-01&end_date=2025-08-32');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);

        // Test date range larger than one month
        $response = $this->getJson('api/v1/user/activities?start_date=2025-08-01&end_date=2025-09-01');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date'])
            ->assertJsonPath('errors.end_date.0', 'The selected date range may not exceed 31 days.');
    }

    /** @test */
    public function activities_endpoint_rejects_unsupported_top_level_query_parameters(): void
    {
        $this->getJson('api/v1/dashboard/activities?start_date=2025-08-01&end_date=2025-08-31&page=2&include=project&random=value')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page', 'include', 'random']);
    }

    /** @test */
    public function user_can_view_activities_within_date_range(): void
    {
        // Create activities for different dates
        Activity::factory()
            ->forUser($this->user)
            ->forProject($this->project)
            ->create(['created_at' => '2025-08-01 10:00:00']);

        Activity::factory()
            ->forUser($this->user)
            ->forProject($this->project)
            ->create(['created_at' => '2025-08-15 10:00:00']);

        // Activity outside range not to be included
        Activity::factory()
            ->forUser($this->user)
            ->forProject($this->project)
            ->create(['created_at' => '2025-09-01 10:00:00']);

        // Activity for different user
        $otherUser = User::factory()->create();
        Activity::factory()
            ->forUser($otherUser)
            ->forProject($this->project)
            ->create(['created_at' => '2025-08-10 10:00:00']);

        $response = $this->getJson('api/v1/dashboard/activities?start_date=2025-08-01&end_date=2025-08-31');

        $response->json();

        $response->assertOk()
            ->assertJsonCount(2, 'data') // one user activity from project setup trait
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'description',
                        'created_at',
                        'user_id',
                        'project',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'user_id' => $this->user->id,
                'created_at' => Carbon::parse('2025-08-01 10:00:00', 'UTC')->toIso8601String(),
            ])
            ->assertJsonFragment([
                'user_id' => $this->user->id,
                'created_at' => Carbon::parse('2025-08-15 10:00:00', 'UTC')->toIso8601String(),
            ])
            ->assertJsonMissing(['user_id' => $otherUser->id]);

    }

    /** @test */
    public function activities_include_soft_deleted_projects(): void
    {
        // Create a project and its activity
        $project = $this->project;

        // Soft delete the project
        $project->delete();

        // Use current month's start and end dates to avoid future flakiness
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        $response = $this->getJson("api/v1/dashboard/activities?start_date={$start}&end_date={$end}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Verify the project data is included despite being soft deleted
        $activities = $response->json('data');
        $this->assertArrayHasKey('project', $activities[0]);
        $this->assertEquals($project->id, $activities[0]['project']['id']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_activities_in_range(): void
    {
        // Create activity outside the requested date range
        Activity::factory()
            ->forUser($this->user)
            ->forProject($this->project)
            ->create(['created_at' => '2025-07-01 10:00:00']);

        $response = $this->getJson('api/v1/dashboard/activities?start_date=2025-08-01&end_date=2025-08-10');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertExactJson([
                'data' => [],
            ]);
    }

    /** @test */
    public function get_user_activities_returns_results_in_created_at_order(): void
    {
        Activity::factory()
            ->forUser($this->user)
            ->forProject($this->project)
            ->create(['created_at' => '2025-08-20 10:00:00']);

        Activity::factory()
            ->forUser($this->user)
            ->forProject($this->project)
            ->create(['created_at' => '2025-08-05 10:00:00']);

        $start = Carbon::parse('2025-08-01')->startOfDay();
        $end = Carbon::parse('2025-08-31')->endOfDay();

        $repo = new ActivityRepository;

        $collection = $repo->getUserActivities($this->user->id, $start, $end);

        $this->assertSame(
            ['2025-08-05 10:00:00', '2025-08-20 10:00:00'],
            $collection->pluck('created_at')
                ->map(static fn (Carbon $createdAt): string => $createdAt->format('Y-m-d H:i:s'))
                ->values()
                ->all()
        );
    }
}
