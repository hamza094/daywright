<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ProjectDashboard;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use App\Repository\DashBoardRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class UserActivitiesTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function activities_endpoint_validates_date_parameters(): void
    {
        // Test missing parameters
        $response = $this->getJson('api/v1/user/activities');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);

        // Test invalid date format
        $response = $this->getJson('api/v1/user/activities?start_date=01-08-2025&end_date=15-08-2025');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);

        // Test end date before start date
        $response = $this->getJson('api/v1/user/activities?start_date=2025-08-15&end_date=2025-08-01');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        // Test valid format but invalid dates
        $response = $this->getJson('api/v1/user/activities?start_date=2025-13-01&end_date=2025-08-32');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);
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

        $response = $this->getJson('api/v1/user/activities?start_date=2025-08-01&end_date=2025-08-31');

        $response->json();

        $response->assertOk()
            ->assertJsonCount(2) // one user activity from project setup trait
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'description',
                    'created_at',
                    'user_id',
                    'project',
                ],
            ])
            ->assertJsonFragment([
                'user_id' => $this->user->id,
                'created_at' => '2025-08-01 10:00:00',
            ])
            ->assertJsonFragment([
                'user_id' => $this->user->id,
                'created_at' => '2025-08-15 10:00:00',
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

        $response = $this->getJson("api/v1/user/activities?start_date={$start}&end_date={$end}");

        $response->assertOk()
            ->assertJsonCount(2);

        // Verify the project data is included despite being soft deleted
        $activities = $response->json();
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

        $response = $this->getJson('api/v1/user/activities?start_date=2025-08-01&end_date=2025-08-10');

        $response->assertOk()
            ->assertJsonCount(0)
            ->assertJson([]);
    }

    public function test_get_user_activities_is_cached(): void
    {
        // create an activity inside the date range
        Activity::factory()->forUser($this->user)->forProject($this->project)
            ->create(['created_at' => '2025-08-05 10:00:00']);

        $start = Carbon::parse('2025-08-01')->startOfDay();
        $end = Carbon::parse('2025-08-31')->endOfDay();

        $repo = new DashBoardRepository;

        $collection = $repo->getUserActivities($this->user->id, $start, $end);

        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback(); // run the original query callback
            });

        // ✅ On second call, should retrieve from cache
        $collection2 = $repo->getUserActivities($this->user->id, $start, $end);

        $this->assertEquals($collection->pluck('id')->all(), $collection2->pluck('id')->all());
    }
}
