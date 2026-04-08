<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ActivityTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */

    // filter
    public function it_filters_activities_by_project_specified(): void
    {
        $task = $this->project->addTask('test task');

        $response = $this->getJson($this->project->path().'/activities')->assertOk();

        $data = $response->json()['data'];

        $this->assertCount(2, $data);
        $this->assertEquals('Task "'.($task->title).'" added', $data[0]['description']);
        $this->assertEquals('New project created', $data[1]['description']);
    }

    /** @test */
    public function it_filters_activities_by_tasks(): void
    {
        $task = $this->project->addTask('test task');

        $response = $this->getJson($this->project->path().'/activities?tasks=1')
            ->assertJsonCount(1, ['data'])
            ->assertOk();

        $this->assertEquals('Task "'.($task->title).'" added', $response->json()['data'][0]['description']);
    }

    /** @test */
    public function it_filters_activities_by_authenticated_user(): void
    {
        $this->project->addTask('test task');

        $response = $this->getJson($this->project->path().'/activities?mine='.$this->project->user->id)->assertOk();

        $this->assertEquals('New project created', $response->json()['data'][1]['description']);
    }

    /** @test */
    public function it_shows_error_when_no_related_activities_are_found(): void
    {
        $this->project->addTask('test task');

        $response = $this->getJson($this->project->path().'/activities?members=1')
            ->assertOk();

        $this->assertEquals($response->json(), ['message' => 'No related activities found']);
    }
}
